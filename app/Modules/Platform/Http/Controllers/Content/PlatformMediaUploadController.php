<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\Media\Services\MediaLibraryService;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Requests\Content\UploadPlatformMediaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

class PlatformMediaUploadController extends Controller
{
    use ManagesPlatformContent;

    public function __construct(
        protected MediaLibraryService $library,
        protected SecurityLogger $security,
    ) {}

    public function __invoke(UploadPlatformMediaRequest $request): RedirectResponse
    {
        $folder = $this->folder($request->input('folder_id'));

        if ($folder instanceof MediaFolder) {
            $this->ensurePlatformOwned($folder);
        }

        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter(
            (array) $request->file('files', []),
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        $stored = [];

        foreach ($files as $file) {
            try {
                $asset = $this->library->upload($file, $folder, null);

                if ($asset->getAttribute('company_id') !== null) {
                    $asset->company_id = null;
                    $asset->save();
                }

                $stored[] = $asset;
            } catch (MediaException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        $this->security->log(
            SecurityEvent::MediaUploaded,
            admin: $request->user('admin'),
            description: __(':count file(s) uploaded.', ['count' => count($stored)]),
            context: [
                'folder_id' => $folder?->id,
                'asset_ids' => array_map(static fn (MediaAsset $asset): int => $asset->id, $stored),
            ],
        );

        return back()->with('success', __(':count file(s) uploaded.', ['count' => count($stored)]));
    }

    protected function folder(mixed $id): ?MediaFolder
    {
        if (! is_numeric($id)) {
            return null;
        }

        $folder = MediaFolder::query()->withoutGlobalScopes()->whereNull('company_id')->find((int) $id);

        return $folder instanceof MediaFolder ? $folder : null;
    }
}
