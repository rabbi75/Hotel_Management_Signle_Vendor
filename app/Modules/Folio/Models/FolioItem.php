<?php

declare(strict_types=1);

namespace App\Modules\Folio\Models;

use App\Modules\Folio\Enums\FolioItemType;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property FolioItemType $type
 * @property int $amount
 */
class FolioItem extends Model
{
    protected $fillable = [
        'guest_folio_id', 'hotel_service_id', 'type', 'description',
        'quantity', 'unit_price', 'amount', 'tax_amount', 'posted_at', 'posted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FolioItemType::class,
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'amount' => 'integer',
            'tax_amount' => 'integer',
            'posted_at' => 'immutable_datetime',
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
     * @return BelongsTo<HotelService, $this>
     */
    public function hotelService(): BelongsTo
    {
        return $this->belongsTo(HotelService::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function postedAt(): ?CarbonImmutable
    {
        return $this->posted_at;
    }
}
