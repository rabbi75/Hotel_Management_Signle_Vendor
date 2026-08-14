<?php

declare(strict_types=1);

namespace App\Modules\Folio\Actions;

use App\Modules\Folio\Enums\FolioStatus;
use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Folio\Services\FolioCalculator;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseGuestFolio
{
    public function __construct(
        protected FolioCalculator $calculator,
        protected GenerateGuestInvoice $generateInvoice,
    ) {}

    /**
     * @param  array{allow_balance?: bool, notes?: string|null}  $options
     */
    public function handle(GuestFolio $folio, array $options = []): GuestFolio
    {
        if (! $folio->isOpen()) {
            return $folio->load(['items', 'payments', 'invoice']);
        }

        return DB::transaction(function () use ($folio, $options): GuestFolio {
            $folio = $this->calculator->recalculate($folio);

            if ($folio->balance > 0 && ! ($options['allow_balance'] ?? false)) {
                throw ValidationException::withMessages([
                    'balance' => __('The folio still has an outstanding balance of :amount.', [
                        'amount' => number_format($folio->balance / 100, 2),
                    ]),
                ]);
            }

            if (array_key_exists('notes', $options) && is_string($options['notes'])) {
                $folio->notes = trim(($folio->notes ? $folio->notes."\n" : '').$options['notes']) ?: null;
            }

            $folio->status = FolioStatus::Closed;
            $folio->closed_at = CarbonImmutable::now();
            $folio->save();

            $this->generateInvoice->handle($folio);
            $this->syncReservationAmounts($folio);

            return $folio->fresh(['items', 'payments', 'invoice', 'guest', 'hotel', 'reservation']) ?? $folio;
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
