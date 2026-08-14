<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Models;

use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $company_id
 * @property int $hotel_id
 * @property string $name
 * @property int $base_price
 */
class RoomType extends Model implements HasMedia
{
    use BelongsToCompany, BelongsToWorkspace, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'name', 'code', 'description', 'base_price',
        'max_adults', 'max_children', 'max_occupancy', 'bed_configuration', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'max_occupancy' => 'integer',
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
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return BelongsToMany<Facility, $this>
     */
    public function facilities(): BelongsToMany
    {
        return $this->morphToMany(Facility::class, 'facilityable')->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery');
    }
}
