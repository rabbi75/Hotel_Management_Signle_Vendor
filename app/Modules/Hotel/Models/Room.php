<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Models;

use App\Modules\Hotel\Enums\RoomStatus;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $company_id
 * @property int $hotel_id
 * @property string $number
 * @property RoomStatus $status
 */
class Room extends Model implements HasMedia
{
    use BelongsToCompany, BelongsToWorkspace, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'building_id', 'floor_id', 'room_type_id', 'number', 'code',
        'description', 'base_price', 'max_occupancy', 'status', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'max_occupancy' => 'integer',
            'status' => RoomStatus::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * @return BelongsTo<Building, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * @return BelongsTo<Floor, $this>
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * @return HasMany<Bed, $this>
     */
    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    /**
     * @return BelongsToMany<Facility, $this>
     */
    public function facilities(): BelongsToMany
    {
        return $this->morphToMany(Facility::class, 'facilityable')->withTimestamps();
    }

    public function imageUrl(): ?string
    {
        return $this->publicMediaUrl('photo');
    }

    public function imageThumbUrl(): ?string
    {
        return $this->publicMediaUrl('photo', 'thumb') ?? $this->imageUrl();
    }

    /**
     * Root-relative URL so the photo loads on the current host/port
     * (e.g. localhost:8000) instead of whatever APP_URL was when it was saved.
     */
    protected function publicMediaUrl(string $collection, string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia($collection);

        if ($media === null) {
            return null;
        }

        $url = $conversion === '' ? $media->getUrl() : $media->getUrl($conversion);
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->fit(Fit::Crop, 320, 240);
    }
}
