<?php

declare(strict_types=1);

namespace App\Modules\Folio\Models;

use App\Modules\Folio\Enums\GuestInvoiceStatus;
use App\Modules\Guest\Models\Guest;
use App\Modules\Reservation\Models\Reservation;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $number
 * @property GuestInvoiceStatus $status
 * @property int $total
 */
class GuestInvoice extends Model
{
    use BelongsToCompany, BelongsToWorkspace;

    protected $fillable = [
        'guest_folio_id', 'guest_id', 'reservation_id', 'number', 'status',
        'subtotal', 'tax', 'discount', 'total', 'currency',
        'issued_at', 'paid_at', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GuestInvoiceStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'issued_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<GuestFolio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(GuestFolio::class, 'guest_folio_id');
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
     * @return HasMany<GuestInvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(GuestInvoiceLine::class);
    }

    public function issuedAt(): ?CarbonImmutable
    {
        return $this->issued_at;
    }
}
