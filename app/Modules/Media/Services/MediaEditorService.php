<?php

declare(strict_types=1);

namespace App\Modules\Media\Services;

use App\Modules\Media\DTOs\ImageEditData;
use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;

/**
 * Non-destructive image editing.
 *
 * Every edit produces a *new* asset linked back to the one it came from. The
 * original bytes are never rewritten, because something else in the system —
 * a published page, a sent email — may already point at them.
 */
class MediaEditorService
{
    public function __construct(
        protected MediaLibraryService $library,
        protected ImageManagerInterface $images,
    ) {}

    /**
     * @throws MediaException
     */
    public function apply(MediaAsset $asset, ImageEditData $edit, ?User $editor = null): MediaAsset
    {
        if (! $asset->isImage()) {
            throw new MediaException(__('Only images can be edited.'));
        }

        $source = $asset->file();

        if ($source === null) {
            throw new MediaException(__('That file is no longer available.'));
        }

        if (! $edit->hasOperations()) {
            throw new MediaException(__('No changes were requested.'));
        }

        $working = $this->stageCopy($source->getPath(), $asset->extension);

        try {
            $image = $this->images->decodePath($working);

            if ($edit->rotate !== null && $edit->rotate % 360 !== 0) {
                $image->rotate((float) -$edit->rotate);
            }

            if ($edit->hasCrop()) {
                $image->crop($edit->cropWidth ?? $image->width(), $edit->cropHeight ?? $image->height(), $edit->cropX ?? 0, $edit->cropY ?? 0);
            }

            if ($edit->width !== null || $edit->height !== null) {
                $image->scaleDown($edit->width, $edit->height);
            }

            $image->save($working, quality: $edit->quality ?? 90);

            $width = $image->width();
            $height = $image->height();
        } catch (MediaException $exception) {
            File::delete($working);

            throw $exception;
        } catch (Throwable $exception) {
            File::delete($working);

            throw new MediaException(__('This image could not be edited.'), 0, $exception);
        }

        $version = new MediaAsset([
            'folder_id' => $asset->folder_id,
            'uploaded_by' => $editor instanceof User ? $editor->id : $asset->uploaded_by,
            'original_asset_id' => $asset->original_asset_id ?? $asset->id,
            'name' => $asset->name.' '.__('(edited)'),
            'title' => $asset->title,
            'alt' => $asset->alt,
            'caption' => $asset->caption,
            'tags' => $asset->tags,
            'content_hash' => (string) hash_file('sha256', $working),
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'size' => (int) File::size($working),
            'width' => $width,
            'height' => $height,
            'disk' => $asset->disk,
        ]);

        $version->company_id = $asset->company_id;
        $version->save();

        try {
            $version->addMedia($working)
                ->usingFileName(Str::slug($version->name).'-'.$version->id.'.'.$version->extension)
                ->toMediaCollection(MediaAsset::COLLECTION, $version->disk);
        } catch (Throwable $exception) {
            $version->forceDelete();

            throw new MediaException($exception->getMessage(), 0, $exception);
        }

        return $version->refresh();
    }

    protected function stageCopy(string $path, string $extension): string
    {
        $directory = storage_path('app/media-staging');

        File::ensureDirectoryExists($directory);

        $target = $directory.DIRECTORY_SEPARATOR.Str::ulid()->toString().'.'.$extension;

        File::copy($path, $target);

        return $target;
    }
}
