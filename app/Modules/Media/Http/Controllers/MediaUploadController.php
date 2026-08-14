<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Http\Requests\UploadMediaRequest;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\Media\Services\MediaLibraryService;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

/**
 * Multi-file upload.
 *
 * The browser posts one request per file — the drag-and-drop surface tracks
 * progress per file and retries individually — but the endpoint accepts a
 * batch too, so a scripted import does not have to fan out.
 */
class MediaUploadController extends Controller
{
    public function __construct(
        protected MediaLibraryService $library,
        protected SecurityLogger $security,
        protected SubscriptionLimits $limits,
    ) {}

    public function __invoke(UploadMediaRequest $request): RedirectResponse
    {
        $folder = $this->folder($request->input('folder_id'));
        $uploader = $request->user();

        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter(
            (array) $request->file('files', []),
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        // Reject the whole batch before storing anything if it would push the
        // workspace past its plan's storage ceiling. Megabytes to match the
        // unit the plan declares; a part-megabyte batch still counts as one.
        $requestedMb = (int) ceil(array_sum(array_map(
            static fn (UploadedFile $file): int => $file->getSize() ?: 0,
            $files,
        )) / (1024 * 1024));

        try {
            $this->limits->ensure('storage_mb', max(1, $requestedMb));
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $stored = [];

        foreach ($files as $file) {
            try {
                $stored[] = $this->library->upload($file, $folder, $uploader instanceof User ? $uploader : null);
            } catch (MediaException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        $this->security->log(
            SecurityEvent::MediaUploaded,
            $uploader,
            __(':count file(s) uploaded.', ['count' => count($stored)]),
            [
                'folder_id' => $folder?->id,
                'asset_ids' => array_map(static fn (MediaAsset $asset): int => $asset->id, $stored),
            ],
        );

        return back()->with('success', __(':count file(s) uploaded.', ['count' => count($stored)]));
    }

    protected function folder(mixed $id): ?MediaFolder
    {
        return is_numeric($id) ? MediaFolder::query()->find((int) $id) : null;
    }
}
