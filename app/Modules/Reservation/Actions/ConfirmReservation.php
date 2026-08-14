<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Actions;

use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Room;
use App\Modules\HotelOperations\Events\ReservationConfirmed;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmReservation
{
    public function __construct(protected AvailabilityService $availability) {}

    /**
     * @param  array{room_id?: int|null, bed_id?: int|null}  $options
     */
    public function handle(Reservation $reservation, array $options = []): Reservation
    {
        if (! $reservation->status->canConfirm()) {
            throw ValidationException::withMessages([
                'status' => __('Only pending reservations can be confirmed.'),
            ]);
        }

        $reservation = DB::transaction(function () use ($reservation, $options): Reservation {
            $checkIn = CarbonImmutable::parse($reservation->check_in_date)->startOfDay();
            $checkOut = CarbonImmutable::parse($reservation->check_out_date)->startOfDay();

            $roomId = $options['room_id'] ?? $reservation->room_id;
            $bedId = $options['bed_id'] ?? $reservation->bed_id;

            if ($roomId === null && $bedId === null && $reservation->room_type_id !== null) {
                $room = $this->availability->firstAvailableRoom(
                    $reservation->hotel_id,
                    $reservation->room_type_id,
                    $checkIn,
                    $checkOut,
                    $reservation->id,
                );

                if ($room instanceof Room) {
                    $roomId = $room->id;
                }
            }

            if ($roomId === null && $bedId === null) {
                throw ValidationException::withMessages([
                    'room_id' => __('No room of this type is free for these dates. Assign a room, then confirm.'),
                ]);
            }

            $this->availability->ensureAvailable(
                $reservation->hotel_id,
                $checkIn,
                $checkOut,
                $roomId !== null ? (int) $roomId : null,
                $bedId !== null ? (int) $bedId : null,
                $reservation->id,
            );

            $reservation->room_id = $roomId !== null ? (int) $roomId : null;
            $reservation->bed_id = $bedId !== null ? (int) $bedId : null;
            $reservation->status = ReservationStatus::Confirmed;
            $reservation->save();

            if ($reservation->room_id !== null) {
                Room::query()->whereKey($reservation->room_id)->update([
                    'status' => RoomStatus::Reserved->value,
                ]);
            }

            return $reservation->fresh(['guest', 'room', 'bed', 'hotel', 'roomType']) ?? $reservation;
        });

        ReservationConfirmed::dispatch($reservation);

        return $reservation;
    }
}
