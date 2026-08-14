<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Media\DTOs\MediaAssetData;
use App\Modules\Media\Enums\MediaType;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Http\Requests\BulkMediaRequest;
use App\Modules\Media\Http\Requests\UpdateMediaAssetRequest;
use App\Modules\Media\Http\Resources\MediaAssetResource;
use App\Modules\Media\Http\Resources\MediaFolderResource;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\Media\Services\MediaLibraryService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    use Concerns\BrowsesMedia;

    public function __construct(
        protected MediaLibraryService $library,
        protected SecurityLogger $security,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MediaAsset::class);

        $folder = $this->currentFolder($request);
        $paginator = $this->browse($request, $folder);

        return Inertia::render('media/index', [
            'assets' => [
                'data' => MediaAssetResource::collection($paginator->getCollection())->resolve($request),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'folders' => $this->folderTree($request),
            'current_folder' => $folder instanceof MediaFolder
                ? (new MediaFolderResource($folder))->resolve($request)
                : null,
            'breadcrumb' => $this->folderBreadcrumb($folder),
            'filters' => $this->activeFilters($request),
            'types' => array_map(
                static fn (MediaType $type): array => ['value' => $type->value, 'label' => $type->label()],
                MediaType::cases(),
            ),
            'uploaders' => $this->uploaderOptions(),
            'limits' => [
                'max_upload_kb' => (int) config('saas.media.max_upload_kb'),
                'extensions' => $this->library->allowedExtensions(),
                'per_page' => $this->perPage(),
            ],
            'can' => [
                'upload' => Gate::allows('create', MediaAsset::class),
                'manage_folders' => Gate::allows('create', MediaFolder::class),
                'update' => $request->user()?->can('media.update') ?? false,
                'delete' => $request->user()?->can('media.delete') ?? false,
            ],
        ]);
    }

    /**
     * Detail for the drawer: the version history and relations the grid does
     * not carry, fetched only when a file is actually opened.
     */
    public function show(Request $request, MediaAsset $asset): JsonResponse
    {
        Gate::authorize('view', $asset);

        $asset->load(['folder', 'uploader', 'versions'])->loadCount('versions');

        return response()->json([
            'asset' => (new MediaAssetResource($asset))->resolve($request),
            'versions' => MediaAssetResource::collection($asset->versions)->resolve($request),
        ]);
    }

    public function update(UpdateMediaAssetRequest $request, MediaAsset $asset): RedirectResponse
    {
        $data = MediaAssetData::fromRequest($request);

        $asset->fill($data->toUpdateAttributes())->save();

        $this->security->log(
            SecurityEvent::MediaUpdated,
            $request->user(),
            __('Media asset :name updated.', ['name' => $asset->name]),
            ['asset_id' => $asset->id, 'fields' => $data->provided],
        );

        return back()->with('success', __('File updated.'));
    }

    public function destroy(Request $request, MediaAsset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);

        $this->library->delete($asset);

        $this->security->log(
            SecurityEvent::MediaDeleted,
            $request->user(),
            __('Media asset :name deleted.', ['name' => $asset->name]),
            ['asset_id' => $asset->id],
        );

        return back()->with('success', __('File deleted.'));
    }

    /**
     * Bulk move or delete from the grid's selection bar.
     */
    public function bulk(BulkMediaRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map(intval(...), (array) $request->input('ids', []));
        $action = (string) $request->string('action');

        $assets = MediaAsset::query()->whereIn('id', $ids)->get();
        $folder = $this->folderFromInput($request->input('folder_id'));
        $affected = 0;

        foreach ($assets as $asset) {
            if (! Gate::allows($action === 'delete' ? 'delete' : 'update', $asset)) {
                continue;
            }

            try {
                $action === 'delete'
                    ? $this->library->delete($asset)
                    : $this->library->move($asset, $folder);
            } catch (MediaException $exception) {
                return back()->with('error', $exception->getMessage());
            }

            $affected++;
        }

        if ($affected === 0) {
            return back()->with('error', __('Nothing was changed. You may not have permission for the selected files.'));
        }

        if ($action === 'delete') {
            $this->security->log(
                SecurityEvent::MediaDeleted,
                $request->user(),
                __(':count file(s) deleted.', ['count' => $affected]),
                ['count' => $affected],
            );
        }

        return back()->with('success', $action === 'delete'
            ? __(':count file(s) deleted.', ['count' => $affected])
            : __(':count file(s) moved.', ['count' => $affected]));
    }

    protected function folderFromInput(mixed $value): ?MediaFolder
    {
        return is_numeric($value)
            ? MediaFolder::query()->find((int) $value)
            : null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function uploaderOptions(): array
    {
        return MediaAsset::query()
            ->with('uploader')
            ->select('uploaded_by')
            ->whereNotNull('uploaded_by')
            ->distinct()
            ->get()
            ->map(static function (MediaAsset $asset): array {
                $uploader = $asset->uploader;

                return [
                    'value' => (string) $asset->uploaded_by,
                    'label' => $uploader instanceof User ? $uploader->name : __('Unknown'),
                ];
            })
            ->unique('value')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function folderTree(Request $request): array
    {
        $roots = MediaFolder::query()
            ->whereNull('parent_id')
            ->with(['children.children'])
            ->withCount(['assets', 'children'])
            ->orderBy('name')
            ->get();

        return MediaFolderResource::collection($roots)->resolve($request);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    protected function folderBreadcrumb(?MediaFolder $folder): array
    {
        if (! $folder instanceof MediaFolder) {
            return [];
        }

        $slugs = $folder->segments();
        $paths = [];
        $accumulated = '';

        foreach ($slugs as $slug) {
            $accumulated = $accumulated === '' ? $slug : $accumulated.'/'.$slug;
            $paths[] = $accumulated;
        }

        return MediaFolder::query()
            ->whereIn('path', $paths)
            ->get()
            ->sortBy(static fn (MediaFolder $item): int => mb_strlen($item->path))
            ->map(static fn (MediaFolder $item): array => ['id' => $item->id, 'name' => $item->name])
            ->values()
            ->all();
    }
}
