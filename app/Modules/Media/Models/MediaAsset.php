<?php

declare(strict_types=1);

namespace App\Modules\Media\Models;

use App\Modules\Media\Enums\MediaType;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A file in the media library.
 *
 * The bytes are held by spatie/laravel-medialibrary in the `file` collection;
 * this model owns everything the library has no opinion about — which workspace
 * and folder the file belongs to, who uploaded it, and the editorial metadata
 * a CMS or blog will want to read back.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $folder_id
 * @property int|null $uploaded_by
 * @property int|null $original_asset_id
 * @property string $name
 * @property string|null $title
 * @property string|null $alt
 * @property string|null $caption
 * @property list<string>|null $tags
 * @property string $content_hash
 * @property string $mime_type
 * @property string $extension
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string $disk
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
class MediaAsset extends Model implements HasMedia
{
    /** @use HasFactory<MediaAssetFactory> */
    use BelongsToCompany, HasFactory, InteractsWithMedia, SoftDeletes;

    public const COLLECTION = 'file';

    protected $fillable = [
        'folder_id',
        'uploaded_by',
        'original_asset_id',
        'name',
        'title',
        'alt',
        'caption',
        'tags',
        'content_hash',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'disk',
    ];

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_asset_id');
    }

    /**
     * Edited derivatives of this asset.
     *
     * @return HasMany<self, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'original_asset_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfType(Builder $query, MediaType $type): void
    {
        match ($type) {
            MediaType::Image => $query->where('mime_type', 'like', 'image/%'),
            MediaType::Video => $query->where('mime_type', 'like', 'video/%'),
            MediaType::Audio => $query->where('mime_type', 'like', 'audio/%'),
            MediaType::Document => $query->where(function (Builder $inner): void {
                $inner->where('mime_type', 'application/pdf')
                    ->orWhere('mime_type', 'like', 'text/%')
                    ->orWhere('mime_type', 'like', '%word%')
                    ->orWhere('mime_type', 'like', '%sheet%')
                    ->orWhere('mime_type', 'like', '%presentation%');
            }),
            MediaType::Other => $query->whereNot(function (Builder $inner): void {
                $inner->where('mime_type', 'like', 'image/%')
                    ->orWhere('mime_type', 'like', 'video/%')
                    ->orWhere('mime_type', 'like', 'audio/%')
                    ->orWhere('mime_type', 'application/pdf')
                    ->orWhere('mime_type', 'like', 'text/%');
            }),
        };
    }

    public function type(): MediaType
    {
        return MediaType::fromMime($this->mime_type);
    }

    public function isImage(): bool
    {
        return $this->type() === MediaType::Image;
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(self::COLLECTION);
    }

    public function url(): ?string
    {
        return $this->getFirstMediaUrl(self::COLLECTION) ?: null;
    }

    public function conversionUrl(string $conversion): ?string
    {
        // An unfinished conversion yields an empty string rather than a 404
        // placeholder; fall back to the original so a card is never blank.
        return $this->getFirstMediaUrl(self::COLLECTION, $conversion) ?: $this->url();
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)
            ->singleFile()
            ->useDisk((string) config('saas.media.disk', 'local'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        /** @var array<string, array{width: int, height: int}> $conversions */
        $conversions = config('saas.media.conversions', []);

        foreach ($conversions as $name => $size) {
            $conversion = $this->addMediaConversion($name)
                ->nonQueued()
                ->performOnCollections(self::COLLECTION);

            $conversion->fit(Fit::Contain, $size['width'], $size['height']);

            if (config('saas.media.optimize')) {
                // A no-op when the optimiser binaries are absent, which is the
                // usual case on a developer machine.
                $conversion->optimize();
            }
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
