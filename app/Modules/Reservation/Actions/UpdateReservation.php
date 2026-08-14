<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Actions;

use App\Modules\Reservation\DTOs\ReservationData;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateReservation
{
    public function __construct(protected AvailabilityService $availability) {}

    public function handle(Reservation $reservation, ReservationData $data): Reservation
    {
        if (in_array($reservation->status, [ReservationStatus::CheckedOut, ReservationStatus::Cancelled, ReservationStatus::NoShow], true)) {
            throw ValidationException::withMessages([
                'status' => __('This reservation can no longer be edited.'),
            ]);
        }

        return DB::transaction(function () use ($reservation, $data): Reservation {
            $attrs = $data->toUpdateAttributes();

            $checkIn = CarbonImmutable::parse($attrs['check_in_date'] ?? $reservation->check_in_date->toDateString())->startOfDay();
            $checkOut = CarbonImmutable::parse($attrs['check_out_date'] ?? $reservation->check_out_date->toDateString())->startOfDay();
            $roomId = array_key_exists('room_id', $attrs) ? $attrs['room_id'] : $reservation->room_id;
            $bedId = array_key_exists('bed_id', $attrs) ? $attrs['bed_id'] : $reservation->bed_id;
            $hotelId = $attrs['hotel_id'] ?? $reservation->hotel_id;

            if ($roomId !== null || $bedId !== null) {
                $this->availability->ensureAvailable(
                    (int) $hotelId,
                    $checkIn,
                    $checkOut,
                    $roomId !== null ? (int) $roomId : null,
                    $bedId !== null ? (int) $bedId : null,
                    $reservation->id,
                );
            }

            $reservation->fill($attrs)->save();

            return $reservation->fresh(['guest', 'room', 'bed', 'hotel', 'roomType']) ?? $reservation;
        });
    }
}
