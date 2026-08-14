<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Services;

use App\Modules\Hotel\Enums\BedStatus;
use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Room;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Server-side inventory guard against overlapping stays.
 *
 * A date range occupies the night of check-in through the night before check-out
 * (standard hotel convention). Overlap uses half-open intervals:
 * existing.check_in < requested.check_out AND existing.check_out > requested.check_in.
 */
class AvailabilityService
{
    /**
     * @throws ValidationException
     */
    public function ensureAvailable(
        int $hotelId,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $roomId = null,
        ?int $bedId = null,
        ?int $ignoreReservationId = null,
    ): void {
        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw ValidationException::withMessages([
                'check_out_date' => __('Check-out must be after check-in.'),
            ]);
        }

        if ($bedId !== null) {
            $this->ensureBedAvailable($hotelId, $bedId, $checkIn, $checkOut, $ignoreReservationId);

            return;
        }

        if ($roomId !== null) {
            $this->ensureRoomAvailable($hotelId, $roomId, $checkIn, $checkOut, $ignoreReservationId);

            return;
        }

        throw ValidationException::withMessages([
            'room_id' => __('Assign a room or bed before confirming this reservation.'),
        ]);
    }

    public function firstAvailableRoom(
        int $hotelId,
        int $roomTypeId,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $ignoreReservationId = null,
    ): ?Room {
        $rooms = Room::query()
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->orderBy('number')
            ->lockForUpdate()
            ->get();

        foreach ($rooms as $room) {
            if ($room->status->blocksBooking()) {
                continue;
            }

            if (! $this->hasOverlap('room_id', $room->id, $checkIn, $checkOut, $ignoreReservationId)) {
                return $room;
            }
        }

        return null;
    }

    /**
     * @throws ValidationException
     */
    protected function ensureRoomAvailable(
        int $hotelId,
        int $roomId,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $ignoreReservationId,
    ): void {
        $room = Room::query()->where('hotel_id', $hotelId)->whereKey($roomId)->first();

        if (! $room instanceof Room || ! $room->is_active) {
            throw ValidationException::withMessages([
                'room_id' => __('The selected room is not available in this property.'),
            ]);
        }

        if ($room->status->blocksBooking()) {
            throw ValidationException::withMessages([
                'room_id' => __('This room is under :status and cannot be booked.', [
                    'status' => $room->status->label(),
                ]),
            ]);
        }

        if ($this->hasOverlap('room_id', $roomId, $checkIn, $checkOut, $ignoreReservationId)) {
            throw ValidationException::withMessages([
                'room_id' => __('This room is already reserved for the selected dates.'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function ensureBedAvailable(
        int $hotelId,
        int $bedId,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $ignoreReservationId,
    ): void {
        $bed = Bed::query()->where('hotel_id', $hotelId)->whereKey($bedId)->first();

        if (! $bed instanceof Bed || ! $bed->is_active) {
            throw ValidationException::withMessages([
                'bed_id' => __('The selected bed is not available in this property.'),
            ]);
        }

        if (in_array($bed->status, [BedStatus::Maintenance, BedStatus::OutOfService], true)) {
            throw ValidationException::withMessages([
                'bed_id' => __('This bed is under :status and cannot be booked.', [
                    'status' => $bed->status->label(),
                ]),
            ]);
        }

        $room = $bed->room;

        if ($room instanceof Room && $room->status->blocksBooking()) {
            throw ValidationException::withMessages([
                'bed_id' => __('The room for this bed is under :status and cannot be booked.', [
                    'status' => $room->status->label(),
                ]),
            ]);
        }

        if ($this->hasOverlap('bed_id', $bedId, $checkIn, $checkOut, $ignoreReservationId)) {
            throw ValidationException::withMessages([
                'bed_id' => __('This bed is already reserved for the selected dates.'),
            ]);
        }
    }

    protected function hasOverlap(
        string $column,
        int $id,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $ignoreReservationId,
    ): bool {
        $query = Reservation::query()
            ->where($column, $id)
            ->whereIn('status', array_map(
                static fn (ReservationStatus $status): string => $status->value,
                array_filter(ReservationStatus::cases(), static fn (ReservationStatus $s): bool => $s->occupiesInventory()),
            ))
            ->where('check_in_date', '<', $checkOut->toDateString())
            ->where('check_out_date', '>', $checkIn->toDateString());

        if ($ignoreReservationId !== null) {
            $query->whereKeyNot($ignoreReservationId);
        }

        return $query->lockForUpdate()->exists();
    }
}
