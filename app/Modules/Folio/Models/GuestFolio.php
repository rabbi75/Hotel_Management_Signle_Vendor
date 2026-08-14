<?php

declare(strict_types=1);

namespace App\Modules\Folio\Models;

use App\Modules\Folio\Enums\FolioStatus;
use App\Modules\HotelOperations\Concerns\LogsHotelActivity;
use App\Modules\Guest\Models\Guest;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Reservation\Models\Reservation;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $number
 * @property FolioStatus $status
 * @property int $total
 * @property int $paid_amount
 * @property int $balance
 */
class GuestFolio extends Model
{
    use BelongsToCompany, BelongsToWorkspace, LogsHotelActivity;

    protected $fillable = [
        'hotel_id', 'guest_id', 'reservation_id', 'number', 'status', 'currency',
        'subtotal', 'tax', 'discount', 'total', 'paid_amount', 'balance',
        'opened_at', 'closed_at', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FolioStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'balance' => 'integer',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
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
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return HasMany<FolioItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FolioItem::class)->orderBy('posted_at')->orderBy('id');
    }

    /**
     * @return HasMany<GuestPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(GuestPayment::class)->orderByDesc('paid_at');
    }

    /**
     * @return HasOne<GuestInvoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(GuestInvoice::class);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function openedAt(): ?CarbonImmutable
    {
        return $this->opened_at;
    }
}
