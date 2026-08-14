<?php

declare(strict_types=1);

namespace App\Modules\HotelPos\Models;

use App\Modules\Folio\Models\GuestFolio;
use App\Modules\HotelPos\Enums\PosOrderStatus;
use App\Modules\Reservation\Models\Reservation;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stub POS order — links to guest folio / reservation for future room-service posting.
 *
 * @property int $id
 * @property string $number
 * @property PosOrderStatus $status
 * @property int $total
 */
class PosOrder extends Model
{
    use BelongsToCompany, BelongsToWorkspace;

    protected $fillable = [
        'restaurant_id', 'reservation_id', 'guest_folio_id', 'number', 'status',
        'total', 'currency', 'notes', 'opened_at', 'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PosOrderStatus::class,
            'total' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<GuestFolio, $this>
     */
    public function guestFolio(): BelongsTo
    {
        return $this->belongsTo(GuestFolio::class);
    }
}
