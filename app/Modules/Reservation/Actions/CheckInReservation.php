<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Actions;

use App\Modules\Folio\Actions\OpenGuestFolio;
use App\Modules\Folio\Actions\RecordGuestPayment;
use App\Modules\Folio\DTOs\GuestPaymentData;
use App\Modules\HotelOperations\Events\GuestCheckedIn;
use App\Modules\Hotel\Enums\BedStatus;
use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Room;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInReservation
{
    public function __construct(
        protected AvailabilityService $availability,
        protected OpenGuestFolio $openFolio,
        protected RecordGuestPayment $recordPayment,
    ) {}

    /**
     * @param  array{room_id?: int|null, bed_id?: int|null, notes?: string|null, paid_amount?: int|null}  $options
     */
    public function handle(Reservation $reservation, array $options = []): Reservation
    {
        if (! $reservation->status->canCheckIn()) {
            throw ValidationException::withMessages([
                'status' => __('This reservation cannot be checked in.'),
            ]);
        }

        $reservation->loadMissing('guest');

        if ($reservation->guest?->is_blacklisted) {
            throw ValidationException::withMessages([
                'guest_id' => __('This guest is blacklisted and cannot be checked in.'),
            ]);
        }

        $reservation = DB::transaction(function () use ($reservation, $options): Reservation {
            $roomId = $options['room_id'] ?? $reservation->room_id;
            $bedId = $options['bed_id'] ?? $reservation->bed_id;

            if ($roomId === null && $bedId === null) {
                throw ValidationException::withMessages([
                    'room_id' => __('Assign a room or bed before check-in.'),
                ]);
            }

            $this->availability->ensureAvailable(
                $reservation->hotel_id,
                CarbonImmutable::parse($reservation->check_in_date)->startOfDay(),
                CarbonImmutable::parse($reservation->check_out_date)->startOfDay(),
                $roomId !== null ? (int) $roomId : null,
                $bedId !== null ? (int) $bedId : null,
                $reservation->id,
            );

            if ($roomId !== null) {
                $room = Room::query()->whereKey($roomId)->lockForUpdate()->first();

                if ($room instanceof Room && $room->status === RoomStatus::Occupied) {
                    throw ValidationException::withMessages([
                        'room_id' => __('This room is already occupied.'),
                    ]);
                }
            }

            $reservation->room_id = $roomId !== null ? (int) $roomId : null;
            $reservation->bed_id = $bedId !== null ? (int) $bedId : null;
            $reservation->status = ReservationStatus::CheckedIn;
            $reservation->checked_in_at = CarbonImmutable::now();

            if (array_key_exists('notes', $options) && is_string($options['notes'])) {
                $reservation->notes = trim(($reservation->notes ? $reservation->notes."\n" : '').$options['notes']) ?: null;
            }

            $reservation->save();

            if ($reservation->room_id !== null) {
                Room::query()->whereKey($reservation->room_id)->update([
                    'status' => RoomStatus::Occupied->value,
                ]);
            }

            if ($reservation->bed_id !== null) {
                Bed::query()->whereKey($reservation->bed_id)->update([
                    'status' => BedStatus::Occupied->value,
                ]);
            }

            $folio = $this->openFolio->handle($reservation);

            $targetPaid = isset($options['paid_amount']) && is_numeric($options['paid_amount'])
                ? (int) $options['paid_amount']
                : $reservation->paid_amount;

            if ($targetPaid > $folio->paid_amount) {
                $this->recordPayment->handle($folio, new GuestPaymentData(amount: $targetPaid - $folio->paid_amount));
                $reservation->refresh();
            }

            return $reservation->fresh(['guest', 'room', 'bed', 'hotel', 'guestFolio']) ?? $reservation;
        });

        GuestCheckedIn::dispatch($reservation);

        return $reservation;
    }
}
