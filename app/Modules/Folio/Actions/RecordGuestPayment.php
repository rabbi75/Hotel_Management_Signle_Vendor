<?php

declare(strict_types=1);

namespace App\Modules\Folio\Actions;

use App\Modules\Folio\DTOs\GuestPaymentData;
use App\Modules\Folio\Enums\GuestPaymentMethod;
use App\Modules\Folio\Enums\GuestPaymentStatus;
use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Folio\Models\GuestPayment;
use App\Modules\Folio\Services\FolioCalculator;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordGuestPayment
{
    public function __construct(protected FolioCalculator $calculator) {}

    public function handle(GuestFolio $folio, GuestPaymentData $data): GuestPayment
    {
        if ($data->amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Payment amount must be greater than zero.'),
            ]);
        }

        if (! $folio->isOpen()) {
            throw ValidationException::withMessages([
                'folio' => __('Payments cannot be recorded on a closed folio.'),
            ]);
        }

        return DB::transaction(function () use ($folio, $data): GuestPayment {
            $payment = new GuestPayment([
                'guest_folio_id' => $folio->id,
                'guest_id' => $folio->guest_id,
                'amount' => $data->amount,
                'currency' => $folio->currency,
                'method' => GuestPaymentMethod::tryFrom($data->method) ?? GuestPaymentMethod::Cash,
                'status' => GuestPaymentStatus::Completed,
                'reference' => $data->reference,
                'notes' => $data->notes,
                'paid_at' => $data->paidAt !== null
                    ? CarbonImmutable::parse($data->paidAt)
                    : CarbonImmutable::now(),
                'recorded_by' => auth()->id(),
            ]);
            $payment->save();

            $folio = $this->calculator->recalculate($folio);
            $this->syncReservationAmounts($folio);

            return $payment->fresh() ?? $payment;
        });
    }

    protected function syncReservationAmounts(GuestFolio $folio): void
    {
        if ($folio->reservation_id === null) {
            return;
        }

        Reservation::query()->whereKey($folio->reservation_id)->update([
            'paid_amount' => $folio->paid_amount,
            'due_amount' => $folio->balance,
        ]);
    }
}
