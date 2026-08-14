<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Services;

use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\RoomType;
use Carbon\CarbonInterface;

/**
 * Stub rate engine — uses room-type base price until daily rate plans exist.
 */
class RateQuoteService
{
    /**
     * @return array<string, mixed>
     */
    public function quote(
        Hotel $hotel,
        RoomType $roomType,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        int $discount = 0,
        int $tax = 0,
    ): array {
        $nights = max(1, $checkIn->diffInDays($checkOut));
        $subtotal = $roomType->base_price * $nights;
        $afterDiscount = max(0, $subtotal - $discount);

        return [
            'room_type_id' => $roomType->id,
            'nightly_rate' => $roomType->base_price,
            'nights' => $nights,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => (int) ($afterDiscount + $tax),
            'currency' => $hotel->currency,
        ];
    }
}
