<?php

declare(strict_types=1);

namespace App\Modules\Folio\Models;

use App\Modules\Folio\Enums\GuestPaymentMethod;
use App\Modules\Folio\Enums\GuestPaymentStatus;
use App\Modules\Guest\Models\Guest;
use App\Modules\HotelOperations\Concerns\LogsHotelActivity;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $amount
 * @property GuestPaymentMethod $method
 * @property GuestPaymentStatus $status
 */
class GuestPayment extends Model
{
    use BelongsToCompany, BelongsToWorkspace, LogsHotelActivity;

    protected $fillable = [
        'guest_folio_id', 'guest_id', 'amount', 'currency', 'method', 'status',
        'reference', 'notes', 'paid_at', 'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => GuestPaymentMethod::class,
            'status' => GuestPaymentStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function paidAt(): ?CarbonImmutable
    {
        return $this->paid_at;
    }
}
