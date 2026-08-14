<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Actions;

use App\Modules\Folio\Actions\CloseGuestFolio;
use App\Modules\Folio\Actions\OpenGuestFolio;
use App\Modules\Folio\Actions\RecordGuestPayment;
use App\Modules\Folio\DTOs\GuestPaymentData;
use App\Modules\Housekeeping\Actions\CreateHousekeepingTask;
use App\Modules\HotelOperations\Events\GuestCheckedOut;
use App\Modules\Hotel\Enums\BedStatus;
use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Room;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckOutReservation
{
    public function __construct(
        protected OpenGuestFolio $openFolio,
        protected RecordGuestPayment $recordPayment,
        protected CloseGuestFolio $closeFolio,
        protected CreateHousekeepingTask $createHousekeepingTask,
    ) {}

    /**
     * @param  array{paid_amount?: int|null, notes?: string|null}  $options
     */
    public function handle(Reservation $reservation, array $options = []): Reservation
    {
        if (! $reservation->status->canCheckOut()) {
            throw ValidationException::withMessages([
                'status' => __('Only checked-in reservations can be checked out.'),
            ]);
        }

        $reservation = DB::transaction(function () use ($reservation, $options): Reservation {
            $folio = $reservation->guestFolio;

            if ($folio === null) {
                $folio = $this->openFolio->handle($reservation);
            }

            if (isset($options['paid_amount']) && is_numeric($options['paid_amount'])) {
                $targetPaid = (int) $options['paid_amount'];
                $delta = $targetPaid - $folio->paid_amount;

                if ($delta > 0) {
                    $this->recordPayment->handle($folio, new GuestPaymentData(amount: $delta));
                    $folio->refresh();
                }
            }

            $folio = $this->closeFolio->handle($folio, [
                'allow_balance' => true,
                'notes' => is_string($options['notes'] ?? null) ? $options['notes'] : null,
            ]);

            $reservation->paid_amount = $folio->paid_amount;
            $reservation->due_amount = $folio->balance;
            $reservation->status = ReservationStatus::CheckedOut;
            $reservation->checked_out_at = CarbonImmutable::now();

            if (array_key_exists('notes', $options) && is_string($options['notes'])) {
                $reservation->notes = trim(($reservation->notes ? $reservation->notes."\n" : '').$options['notes']) ?: null;
            }

            $reservation->save();

            if ($reservation->room_id !== null) {
                Room::query()->whereKey($reservation->room_id)->update([
                    'status' => RoomStatus::Dirty->value,
                ]);
            }

            $this->createHousekeepingTask->fromCheckout($reservation);

            if ($reservation->bed_id !== null) {
                Bed::query()->whereKey($reservation->bed_id)->update([
                    'status' => BedStatus::Available->value,
                ]);
            }

            return $reservation->fresh(['guest', 'room', 'bed', 'hotel', 'guestFolio.invoice']) ?? $reservation;
        });

        GuestCheckedOut::dispatch($reservation);

        return $reservation;
    }
}
