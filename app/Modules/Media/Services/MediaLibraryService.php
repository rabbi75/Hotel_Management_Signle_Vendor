<?php

declare(strict_types=1);

namespace App\Modules\Media\Services;

use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;

/**
 * Everything that writes to the media library goes through here.
 *
 * Validation, downscaling, duplicate detection and the folder tree's integrity
 * rules live in one place so a second entry point (the API picker, a CMS block,
 * an import) cannot accidentally skip one of them.
 */
class MediaLibraryService
{
    /**
     * Formats intervention/image can decode. SVG is deliberately excluded: it
     * is XML, not a raster, and handing it to a raster decoder either fails or
     * rasterises something the user did not ask for.
     */
    private const PROCESSABLE = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'];

    public function __construct(protected ImageManagerInterface $images) {}

    public function maxUploadBytes(): int
    {
        return (int) config('saas.media.max_upload_kb', 51200) * 1024;
    }

    /**
     * Extensions the library accepts, assembled from the shared upload config
     * so the media module and the avatar uploader never disagree.
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        /** @var list<string> $images */
        $images = config('saas.uploads.image_mimes', []);
        /** @var list<string> $documents */
        $documents = config('saas.uploads.document_mimes', []);
        /** @var list<string> $videos */
        $videos = config('saas.uploads.video_mimes', []);

        return array_values(array_unique([...$images, ...$documents, ...$videos, 'mp3', 'wav', 'ogg', 'zip']));
    }

    /**
     * @throws MediaException
     */
    public function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new MediaException(__('The upload did not complete. Please try again.'));
        }

        if ($file->getSize() > $this->maxUploadBytes()) {
            throw new MediaException(__('":name" is larger than the :max MB limit.', [
                'name' => $file->getClientOriginalName(),
                'max' => (int) round($this->maxUploadBytes() / 1048576),
            ]));
        }

        $extension = Str::lower($file->getClientOriginalExtension());

        if (! in_array($extension, $this->allowedExtensions(), true)) {
            throw new MediaException(__('Files of type ":ext" are not allowed.', ['ext' => $extension]));
        }
    }

    /**
     * Store one uploaded file as an asset.
     *
     * An identical file already in the same folder is returned as-is rather
     * than stored twice — re-uploading is the most common way a library fills
     * up with duplicates.
     *
     * @throws MediaException
     */
    public function upload(UploadedFile $file, ?MediaFolder $folder = null, ?User $uploader = null): MediaAsset
    {
        $this->validate($file);

        $hash = (string) hash_file('sha256', $file->getRealPath());
        $existing = $this->findDuplicate($hash, $folder);

        if ($existing instanceof MediaAsset) {
            return $existing;
        }

        $extension = Str::lower($file->getClientOriginalExtension()) ?: 'bin';
        $workingPath = $this->stage($file, $extension);
        $dimensions = $this->downscale($workingPath, $extension);

        $asset = new MediaAsset([
            'folder_id' => $folder?->id,
            'uploaded_by' => $uploader?->id,
            'original_asset_id' => null,
            'name' => $this->baseName($file->getClientOriginalName()),
            'title' => null,
            'alt' => null,
            'caption' => null,
            'tags' => null,
            'content_hash' => $hash,
            'mime_type' => $file->getClientMimeType(),
            'extension' => $extension,
            'size' => (int) (File::size($workingPath) ?: $file->getSize()),
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'disk' => (string) config('saas.media.disk', 'public'),
        ]);

        $asset->save();

        try {
            $asset->addMedia($workingPath)
                ->usingFileName($this->storedFileName($asset, $extension))
                ->toMediaCollection(MediaAsset::COLLECTION, $asset->disk);
        } catch (Throwable $exception) {
            // Never leave a row pointing at bytes that were not written.
            $asset->forceDelete();

            throw new MediaException($exception->getMessage(), 0, $exception);
        }

        return $asset->refresh();
    }

    public function rename(MediaAsset $asset, string $name): MediaAsset
    {
        $asset->name = trim($name) !== '' ? trim($name) : $asset->name;
        $asset->save();

        return $asset;
    }

    public function move(MediaAsset $asset, ?MediaFolder $folder): MediaAsset
    {
        $this->assertSameWorkspace($folder);

        $asset->folder_id = $folder?->id;
        $asset->save();

        return $asset;
    }

    /**
     * Duplicate an asset, bytes and all, into another folder.
     */
    public function copy(MediaAsset $asset, ?MediaFolder $folder = null): MediaAsset
    {
        $this->assertSameWorkspace($folder);

        $source = $asset->file();

        if ($source === null) {
            throw new MediaException(__('That file is no longer available.'));
        }

        $copy = $asset->replicate(['deleted_at']);
        $copy->folder_id = $folder?->id;
        $copy->name = $asset->name.' '.__('(copy)');
        $copy->save();

        $source->copy($copy, MediaAsset::COLLECTION, $copy->disk);

        return $copy->refresh();
    }

    public function delete(MediaAsset $asset): void
    {
        // Soft delete: the row and its bytes both survive, so an accidental
        // deletion of an asset a page still references is recoverable.
        $asset->delete();
    }

    // -----------------------------------------------------------------
    // Folders
    // -----------------------------------------------------------------

    public function createFolder(string $name, ?MediaFolder $parent = null): MediaFolder
    {
        $this->assertSameWorkspace($parent);

        $folder = new MediaFolder([
            'parent_id' => $parent?->id,
            'name' => trim($name),
            'slug' => $this->uniqueFolderSlug(MediaFolder::slugFor($name), $parent),
        ]);

        $folder->path = $folder->pathUnder($parent, $folder->slug);
        $folder->save();

        return $folder;
    }

    public function renameFolder(MediaFolder $folder, string $name): MediaFolder
    {
        $slug = $this->uniqueFolderSlug(MediaFolder::slugFor($name), $folder->parent, $folder->id);

        $folder->name = trim($name);
        $folder->slug = $slug;
        $this->repath($folder, $folder->parent);

        return $folder;
    }

    /**
     * @throws MediaException when the move would place a folder inside itself.
     */
    public function moveFolder(MediaFolder $folder, ?MediaFolder $parent): MediaFolder
    {
        $this->assertSameWorkspace($parent);

        if ($parent instanceof MediaFolder && $folder->containsFolder($parent)) {
            throw new MediaException(__('A folder cannot be moved inside itself.'));
        }

        $folder->parent_id = $parent?->id;
        $folder->slug = $this->uniqueFolderSlug($folder->slug, $parent, $folder->id);
        $this->repath($folder, $parent);

        return $folder;
    }

    /**
     * @throws MediaException when the folder still has contents.
     */
    public function deleteFolder(MediaFolder $folder): void
    {
        if (! $folder->isEmpty()) {
            throw new MediaException(__('This folder is not empty. Move or delete its contents first.'));
        }

        $folder->delete();
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * Rewrite a folder's path, and every descendant's, after a rename or move.
     */
    protected function repath(MediaFolder $folder, ?MediaFolder $parent): void
    {
        $previous = $folder->path;
        $folder->path = $folder->pathUnder($parent, $folder->slug);
        $folder->save();

        if ($previous === $folder->path) {
            return;
        }

        MediaFolder::query()
            ->where('path', 'like', $previous.'/%')
            ->get()
            ->each(function (MediaFolder $descendant) use ($previous, $folder): void {
                $descendant->path = $folder->path.Str::after($descendant->path, $previous);
                $descendant->save();
            });
    }

    protected function findDuplicate(string $hash, ?MediaFolder $folder): ?MediaAsset
    {
        return MediaAsset::query()
            ->where('content_hash', $hash)
            ->where('folder_id', $folder?->id)
            ->first();
    }

    protected function uniqueFolderSlug(string $slug, ?MediaFolder $parent, ?int $ignoreId = null): string
    {
        $base = $slug;
        $candidate = $base;
        $suffix = 1;

        while ($this->folderSlugTaken($candidate, $parent, $ignoreId)) {
            $candidate = $base.'-'.++$suffix;
        }

        return $candidate;
    }

    protected function folderSlugTaken(string $slug, ?MediaFolder $parent, ?int $ignoreId): bool
    {
        $query = MediaFolder::query()
            ->where('slug', $slug)
            ->where('parent_id', $parent?->id);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    /**
     * Copy the upload somewhere the image pipeline can rewrite it in place,
     * leaving PHP's own temporary file untouched.
     */
    protected function stage(UploadedFile $file, string $extension): string
    {
        $directory = storage_path('app/media-staging');

        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.Str::ulid()->toString().'.'.$extension;

        File::copy($file->getRealPath(), $path);

        return $path;
    }

    /**
     * Shrink an oversized image to the configured longest edge, in place.
     *
     * @return array{0: int|null, 1: int|null} width and height after processing
     */
    protected function downscale(string $path, string $extension): array
    {
        if (! in_array($extension, self::PROCESSABLE, true)) {
            return [null, null];
        }

        $max = (int) config('saas.media.max_image_dimension', 2560);

        try {
            $image = $this->images->decodePath($path);

            if ($image->width() > $max || $image->height() > $max) {
                $image->scaleDown($max, $max);
                $image->save($path);
            }

            return [$image->width(), $image->height()];
        } catch (Throwable) {
            // A file that claims to be an image but cannot be decoded is still
            // storable; it simply has no dimensions.
            return [null, null];
        }
    }

    protected function storedFileName(MediaAsset $asset, string $extension): string
    {
        return Str::slug($asset->name).'-'.$asset->id.'.'.$extension;
    }

    protected function baseName(string $originalName): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);

        return $name !== '' ? Str::limit($name, 180, '') : __('Untitled');
    }

    /**
     * The tenant scope already constrains folder lookups, but a folder handed
     * in by id from a job or an import must still be re-checked.
     */
    protected function assertSameWorkspace(?MediaFolder $folder): void
    {
        if ($folder instanceof MediaFolder && $folder->company_id !== current_company_id()) {
            throw new MediaException(__('That folder belongs to another workspace.'));
        }
    }
}
