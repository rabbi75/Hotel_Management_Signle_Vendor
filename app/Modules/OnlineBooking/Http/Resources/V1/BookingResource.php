<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Resources\V1;

use App\Modules\Reservation\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reservation
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Reservation $reservation */
        $reservation = $this->resource;
        $reservation->loadMissing(['guest', 'hotel', 'roomType']);

        return [
            'number' => $reservation->number,
            'type' => 'booking',
            'status' => $reservation->status->value,
            'booking_source' => $reservation->booking_source->value,
            'external_reference' => $reservation->external_reference,
            'check_in_date' => $reservation->check_in_date->toDateString(),
            'check_out_date' => $reservation->check_out_date->toDateString(),
            'adults' => $reservation->adults,
            'children' => $reservation->children,
            'total' => $reservation->total,
            'due_amount' => $reservation->due_amount,
            'currency' => $reservation->hotel?->currency,
            'hotel_id' => $reservation->hotel?->uuid,
            'room_type_id' => $reservation->room_type_id,
            'guest' => [
                'first_name' => $reservation->guest?->first_name,
                'last_name' => $reservation->guest?->last_name,
                'email' => $reservation->guest?->email,
            ],
            'created_at' => $reservation->created_at?->toIso8601String(),
        ];
    }
}
