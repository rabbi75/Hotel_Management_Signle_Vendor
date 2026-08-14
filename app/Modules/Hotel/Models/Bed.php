<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Models;

use App\Modules\Hotel\Enums\BedStatus;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int $hotel_id
 * @property int $room_id
 * @property string $name
 * @property BedStatus $status
 */
class Bed extends Model
{
    use BelongsToCompany, BelongsToWorkspace, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'room_id', 'floor_id', 'name', 'code', 'bed_type',
        'price', 'description', 'status', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'status' => BedStatus::class,
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
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Floor, $this>
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }
}
