<?php

declare(strict_types=1);

namespace App\Modules\Media;

use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\Models\Company;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\Media\Policies\MediaAssetPolicy;
use App\Modules\Media\Policies\MediaFolderPolicy;
use App\Modules\Media\Services\MediaEditorService;
use App\Modules\Media\Services\MediaLibraryService;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class MediaServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        MediaAsset::class => MediaAssetPolicy::class,
        MediaFolder::class => MediaFolderPolicy::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(MediaLibraryService::class);
        $this->app->singleton(MediaEditorService::class);
    }

    protected function bootModule(): void
    {
        $this->alignMediaLibraryLimits();
        $this->assertConfiguredDiskExists();
        $this->registerLimitResolvers();
        $this->registerNavigation();
    }

    /**
     * Teach SubscriptionLimits how to measure storage, so a plan's `storage_mb`
     * ceiling can be enforced and shown on the usage meters. Stored bytes are
     * folded to whole megabytes to match the unit the plan is authored in.
     */
    protected function registerLimitResolvers(): void
    {
        SubscriptionLimits::resolveUsing(
            'storage_mb',
            static fn (Company $company): int => (int) floor(
                ((int) MediaAsset::query()->forCompany($company->id)->sum('size')) / (1024 * 1024),
            ),
        );
    }

    /**
     * medialibrary carries its own 10 MB ceiling, which would reject a file the
     * kit's own configuration explicitly allows. One number, one source.
     */
    protected function alignMediaLibraryLimits(): void
    {
        Config::set('media-library.max_file_size', (int) config('saas.media.max_upload_kb', 51200) * 1024);
    }

    /**
     * Fail loudly at boot rather than on the first upload if `saas.media.disk`
     * names a disk that does not exist.
     *
     * Local and S3 both ship in config/filesystems.php. Cloudflare R2 needs no
     * driver of its own — it *is* the S3 driver — so it is configured by
     * pointing the existing `s3` disk at the account endpoint
     * (`AWS_ENDPOINT=https://<account>.r2.cloudflarestorage.com`,
     * `AWS_DEFAULT_REGION=auto`, `AWS_USE_PATH_STYLE_ENDPOINT=true`), or by
     * adding a second S3-driver disk beside it. Nothing here special-cases it,
     * which is the point: no env() read at runtime, and no disk definition that
     * `config:cache` would silently blank out.
     */
    protected function assertConfiguredDiskExists(): void
    {
        $disk = (string) config('saas.media.disk', 'local');

        if (config("filesystems.disks.{$disk}") === null) {
            throw new InvalidArgumentException(
                "The media library is configured to use the [{$disk}] disk, which is not defined in config/filesystems.php."
            );
        }
    }

    protected function registerNavigation(): void
    {
        // Media library for the public site is managed from the operator console.
    }
}
