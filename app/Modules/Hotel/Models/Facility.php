<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Models;

use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $hotel_id
 * @property string $name
 */
class Facility extends Model
{
    use BelongsToCompany, BelongsToWorkspace, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'name', 'code', 'description', 'icon', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return MorphToMany<Hotel, $this>
     */
    public function hotels(): MorphToMany
    {
        return $this->morphedByMany(Hotel::class, 'facilityable')->withTimestamps();
    }

    /**
     * @return MorphToMany<RoomType, $this>
     */
    public function roomTypes(): MorphToMany
    {
        return $this->morphedByMany(RoomType::class, 'facilityable')->withTimestamps();
    }

    /**
     * @return MorphToMany<Room, $this>
     */
    public function rooms(): MorphToMany
    {
        return $this->morphedByMany(Room::class, 'facilityable')->withTimestamps();
    }
}
