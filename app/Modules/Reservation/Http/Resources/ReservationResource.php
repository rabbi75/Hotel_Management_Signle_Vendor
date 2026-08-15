<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Http\Resources;

use App\Modules\Reservation\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reservation */
class ReservationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Reservation $reservation */
        $reservation = $this->resource;

        return [
            'id' => $reservation->id,
            'number' => $reservation->number,
            'hotel_id' => $reservation->hotel_id,
            'hotel' => $reservation->relationLoaded('hotel') ? $reservation->hotel?->name : null,
            'guest_id' => $reservation->guest_id,
            'guest' => $reservation->relationLoaded('guest') ? $reservation->guest?->fullName() : null,
            'guest_email' => $reservation->relationLoaded('guest') ? $reservation->guest?->email : null,
            'guest_phone' => $reservation->relationLoaded('guest') ? $reservation->guest?->phone : null,
            'room_id' => $reservation->room_id,
            'room' => $reservation->relationLoaded('room') ? $reservation->room?->number : null,
            'bed_id' => $reservation->bed_id,
            'bed' => $reservation->relationLoaded('bed') ? $reservation->bed?->name : null,
            'room_type_id' => $reservation->room_type_id,
            'room_type' => $reservation->relationLoaded('roomType') ? $reservation->roomType?->name : null,
            'check_in_date' => $reservation->check_in_date?->toDateString(),
            'check_out_date' => $reservation->check_out_date?->toDateString(),
            'checked_in_at' => $reservation->checked_in_at?->toIso8601String(),
            'checked_out_at' => $reservation->checked_out_at?->toIso8601String(),
            'adults' => $reservation->adults,
            'children' => $reservation->children,
            'rooms_count' => $reservation->rooms_count,
            'nights' => $reservation->nights(),
            'booking_source' => $reservation->booking_source->value,
            'booking_source_label' => $reservation->booking_source->label(),
            'special_requests' => $reservation->special_requests,
            'notes' => $reservation->notes,
            'discount' => $reservation->discount,
            'tax' => $reservation->tax,
            'total' => $reservation->total,
            'paid_amount' => $reservation->paid_amount,
            'due_amount' => $reservation->due_amount,
            'payment_method' => $reservation->relationLoaded('paymentMethod') ? $reservation->paymentMethod?->name : null,
            'payment_status' => $reservation->payment_status?->value,
            'payment_status_label' => $reservation->payment_status?->label(),
            'payment_reference' => $reservation->payment_reference,
            'status' => $reservation->status->value,
            'status_label' => $reservation->status->label(),
            'status_color' => $reservation->status->color(),
            'can_check_in' => $reservation->status->canCheckIn(),
            'can_check_out' => $reservation->status->canCheckOut(),
            'can_cancel' => $reservation->status->canCancel(),
            'can_confirm' => $reservation->status->canConfirm(),
            'created_at' => $reservation->created_at?->toIso8601String(),
        ];
    }
}
