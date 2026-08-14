<?php

declare(strict_types=1);

namespace App\Modules\Folio\Actions;

use App\Modules\Folio\Enums\FolioItemType;
use App\Modules\Folio\Enums\FolioStatus;
use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Folio\Services\FolioCalculator;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenGuestFolio
{
    public function __construct(protected FolioCalculator $calculator) {}

    public function handle(Reservation $reservation): GuestFolio
    {
        $reservation->loadMissing(['guest', 'hotel', 'room', 'roomType']);

        if ($reservation->guest_id === null) {
            throw ValidationException::withMessages([
                'guest_id' => __('A guest is required to open a folio.'),
            ]);
        }

        return DB::transaction(function () use ($reservation): GuestFolio {
            $existing = GuestFolio::query()
                ->where('reservation_id', $reservation->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof GuestFolio) {
                return $existing->load(['items', 'payments']);
            }

            $currency = (string) config('saas.billing.currency', 'USD');

            $folio = new GuestFolio([
                'hotel_id' => $reservation->hotel_id,
                'guest_id' => $reservation->guest_id,
                'reservation_id' => $reservation->id,
                'number' => static::nextNumber(),
                'status' => FolioStatus::Open,
                'currency' => $currency,
                'opened_at' => CarbonImmutable::now(),
            ]);
            $folio->save();

            $this->seedReservationCharges($folio, $reservation);
            $this->calculator->recalculate($folio);

            return $folio->fresh(['items', 'payments', 'guest', 'hotel', 'reservation']) ?? $folio;
        });
    }

    protected function seedReservationCharges(GuestFolio $folio, Reservation $reservation): void
    {
        $nights = $reservation->nights();
        $roomLabel = $reservation->room?->number
            ?? $reservation->roomType?->name
            ?? __('Accommodation');

        $accommodation = max(0, $reservation->total - $reservation->tax);

        if ($accommodation > 0) {
            $folio->items()->create([
                'type' => FolioItemType::Room,
                'description' => __('Room :room — :nights night(s)', [
                    'room' => $roomLabel,
                    'nights' => $nights,
                ]),
                'quantity' => $nights,
                'unit_price' => (int) intdiv($accommodation, max(1, $nights)),
                'amount' => $accommodation,
                'posted_at' => CarbonImmutable::now(),
                'posted_by' => auth()->id(),
            ]);
        }

        if ($reservation->tax > 0) {
            $folio->items()->create([
                'type' => FolioItemType::Tax,
                'description' => __('Tax'),
                'quantity' => 1,
                'unit_price' => $reservation->tax,
                'amount' => $reservation->tax,
                'posted_at' => CarbonImmutable::now(),
                'posted_by' => auth()->id(),
            ]);
        }
    }

    public static function nextNumber(): string
    {
        $prefix = 'FOL-'.now()->format('Ymd').'-';

        do {
            $number = $prefix.Str::upper(Str::random(4));
        } while (GuestFolio::query()->where('number', $number)->exists());

        return $number;
    }
}
