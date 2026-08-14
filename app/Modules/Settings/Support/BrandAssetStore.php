<?php

declare(strict_types=1);

namespace App\Modules\Settings\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storage for the installation's brand assets — the logo, its dark variant, the
 * square mark, the favicon and the landing-page lockup.
 *
 * These always live on the `public` disk, never on the operator-selected
 * `storage.disk`. Two reasons: they have to be readable without a signed URL,
 * and pointing them at a tenant's S3/R2 bucket would make the console's own
 * chrome depend on credentials a tenant can rotate.
 */
class BrandAssetStore
{
    /** Everything this class writes lives under here, which is also how it knows what it may delete. */
    public const DIRECTORY = 'branding';

    /** The asset slots the appearance panel accepts, in display order. */
    public const SLOTS = ['logo', 'dark_logo', 'icon', 'favicon', 'landing_logo'];

    /**
     * Store an upload and return the public URL to it.
     */
    public function put(UploadedFile $file, string $slot): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $name = sprintf('%s-%s.%s', Str::slug($slot), Str::random(16), $extension);

        $this->disk()->putFileAs(self::DIRECTORY, $file, $name, 'public');

        return $this->disk()->url(self::DIRECTORY.'/'.$name);
    }

    /**
     * Delete a previously stored asset.
     *
     * A URL that does not resolve to a file this class wrote — one typed in
     * before uploads existed, or pointing at an external CDN — is left alone.
     * Deleting on the strength of a URL alone would let a pasted path address
     * arbitrary files on the disk.
     */
    public function forget(?string $url): void
    {
        $path = $this->pathFor($url);

        if ($path !== null && $this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    /**
     * The disk-relative path a stored URL refers to, or null when the URL is
     * not one of ours.
     */
    protected function pathFor(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $prefix = $this->disk()->url(self::DIRECTORY).'/';

        if (! str_starts_with($url, $prefix)) {
            return null;
        }

        $name = substr($url, strlen($prefix));

        // A single flat segment only. Anything with a separator or a traversal
        // token is not a name this class ever generated.
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..')) {
            return null;
        }

        return self::DIRECTORY.'/'.$name;
    }

    protected function disk(): Filesystem
    {
        return Storage::disk('public');
    }
}
