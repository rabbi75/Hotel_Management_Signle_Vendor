<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Media\DTOs\MediaAssetData;
use App\Modules\Media\Enums\MediaType;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Http\Resources\MediaAssetResource;
use App\Modules\Media\Http\Resources\MediaFolderResource;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\Media\Services\MediaLibraryService;
use App\Modules\Platform\Http\Controllers\Content\Concerns\BrowsesPlatformMedia;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Requests\Content\BulkPlatformMediaRequest;
use App\Modules\Platform\Http\Requests\Content\UpdatePlatformMediaAssetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformMediaController extends Controller
{
    use BrowsesPlatformMedia;
    use ManagesPlatformContent;

    public function __construct(
        protected MediaLibraryService $library,
        protected SecurityLogger $security,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeContentOperator($request);

        $folder = $this->currentFolder($request);
        $paginator = $this->browse($request, $folder);

        return Inertia::render('media/index', [
            'panel' => 'admin',
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
                'upload' => true,
                'manage_folders' => true,
                'update' => true,
                'delete' => true,
            ],
        ]);
    }

    public function show(Request $request, MediaAsset $asset): JsonResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($asset);

        $asset->load(['folder', 'uploader', 'versions'])->loadCount('versions');

        return response()->json([
            'asset' => (new MediaAssetResource($asset))->resolve($request),
            'versions' => MediaAssetResource::collection($asset->versions)->resolve($request),
        ]);
    }

    public function update(UpdatePlatformMediaAssetRequest $request, MediaAsset $asset): RedirectResponse
    {
        $this->ensurePlatformOwned($asset);

        $data = MediaAssetData::fromRequest($request);

        $asset->fill($data->toUpdateAttributes())->save();

        $this->security->log(
            SecurityEvent::MediaUpdated,
            admin: $request->user('admin'),
            description: __('Media asset :name updated.', ['name' => $asset->name]),
            context: ['asset_id' => $asset->id, 'fields' => $data->provided],
        );

        return back()->with('success', __('File updated.'));
    }

    public function destroy(Request $request, MediaAsset $asset): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($asset);

        $this->library->delete($asset);

        $this->security->log(
            SecurityEvent::MediaDeleted,
            admin: $request->user('admin'),
            description: __('Media asset :name deleted.', ['name' => $asset->name]),
            context: ['asset_id' => $asset->id],
        );

        return back()->with('success', __('File deleted.'));
    }

    public function bulk(BulkPlatformMediaRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map(intval(...), (array) $request->input('ids', []));
        $action = (string) $request->string('action');

        $assets = $this->platformOwned(MediaAsset::class)->whereIn('id', $ids)->get();
        $folder = $this->folderFromInput($request->input('folder_id'));
        $affected = 0;

        foreach ($assets as $asset) {
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
            return back()->with('error', __('Nothing was changed.'));
        }

        if ($action === 'delete') {
            $this->security->log(
                SecurityEvent::MediaDeleted,
                admin: $request->user('admin'),
                description: __(':count file(s) deleted.', ['count' => $affected]),
                context: ['count' => $affected],
            );
        }

        return back()->with('success', $action === 'delete'
            ? __(':count file(s) deleted.', ['count' => $affected])
            : __(':count file(s) moved.', ['count' => $affected]));
    }

    protected function folderFromInput(mixed $value): ?MediaFolder
    {
        if (! is_numeric($value)) {
            return null;
        }

        $folder = $this->platformOwned(MediaFolder::class)->find((int) $value);

        return $folder instanceof MediaFolder ? $folder : null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function uploaderOptions(): array
    {
        return $this->platformOwned(MediaAsset::class)
            ->select('uploaded_by')
            ->whereNotNull('uploaded_by')
            ->distinct()
            ->pluck('uploaded_by')
            ->map(static fn (mixed $id): array => [
                'value' => (string) $id,
                'label' => __('User #:id', ['id' => $id]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function folderTree(Request $request): array
    {
        $roots = $this->platformOwned(MediaFolder::class)
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

        return $this->platformOwned(MediaFolder::class)
            ->whereIn('path', $paths)
            ->get()
            ->sortBy(static fn (MediaFolder $item): int => mb_strlen($item->path))
            ->map(static fn (MediaFolder $item): array => ['id' => $item->id, 'name' => $item->name])
            ->values()
            ->all();
    }
}
