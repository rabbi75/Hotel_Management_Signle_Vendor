<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Actions;

use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Room;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelReservation
{
    public function handle(Reservation $reservation): Reservation
    {
        if (! $reservation->status->canCancel()) {
            throw ValidationException::withMessages([
                'status' => __('Only pending or confirmed reservations can be cancelled.'),
            ]);
        }

        return DB::transaction(function () use ($reservation): Reservation {
            $reservation->status = ReservationStatus::Cancelled;
            $reservation->save();

            if ($reservation->room_id !== null) {
                $stillHeld = Reservation::query()
                    ->where('room_id', $reservation->room_id)
                    ->whereKeyNot($reservation->id)
                    ->whereIn('status', [
                        ReservationStatus::Pending->value,
                        ReservationStatus::Confirmed->value,
                        ReservationStatus::CheckedIn->value,
                    ])
                    ->exists();

                if (! $stillHeld) {
                    Room::query()->whereKey($reservation->room_id)->where('status', RoomStatus::Reserved->value)
                        ->update(['status' => RoomStatus::Available->value]);
                }
            }

            return $reservation->fresh() ?? $reservation;
        });
    }
}
