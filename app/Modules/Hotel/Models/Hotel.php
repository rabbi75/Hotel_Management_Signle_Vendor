<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Models;

use App\Modules\Hotel\Enums\HotelStatus;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $company_id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property HotelStatus $status
 * @property bool $is_active
 */
class Hotel extends Model implements HasMedia
{
    use BelongsToCompany, BelongsToWorkspace, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'address', 'city', 'state', 'country',
        'postal_code', 'phone', 'email', 'website', 'check_in_time', 'check_out_time',
        'currency', 'timezone', 'tax_rate', 'tax_name', 'policies',
        'contact_name', 'contact_phone', 'contact_email', 'status', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HotelStatus::class,
            'is_active' => 'boolean',
            'tax_rate' => 'decimal:4',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<Building, $this>
     */
    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    /**
     * @return HasMany<Floor, $this>
     */
    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    /**
     * @return HasMany<RoomType, $this>
     */
    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<Bed, $this>
     */
    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    /**
     * @return HasMany<Facility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * @return BelongsToMany<Facility, $this>
     */
    public function amenityFacilities(): BelongsToMany
    {
        return $this->morphToMany(Facility::class, 'facilityable')->withTimestamps();
    }

    public function logoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public function coverUrl(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->fit(Fit::Contain, 128, 128);
    }

    protected static function booted(): void
    {
        static::creating(function (self $hotel): void {
            $hotel->uuid ??= (string) Str::ulid();
            $hotel->slug = $hotel->slug ?: Str::slug($hotel->name) ?: Str::lower(Str::random(8));
        });
    }
}
