<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Actions;

use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\Reservation\DTOs\ReservationData;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateReservation
{
    public function __construct(protected AvailabilityService $availability) {}

    public function handle(ReservationData $data): Reservation
    {
        $reservation = DB::transaction(function () use ($data): Reservation {
            $checkIn = CarbonImmutable::parse($data->checkInDate)->startOfDay();
            $checkOut = CarbonImmutable::parse($data->checkOutDate)->startOfDay();

            if ($data->roomId !== null || $data->bedId !== null) {
                $this->availability->ensureAvailable(
                    $data->hotelId,
                    $checkIn,
                    $checkOut,
                    $data->roomId,
                    $data->bedId,
                );
            }

            $attributes = $data->toAttributes();

            if (! in_array('total', $data->provided, true) || $data->total === 0) {
                $attributes['total'] = $this->estimateTotal($data, $checkIn, $checkOut);
                $attributes['due_amount'] = max(0, $attributes['total'] - $data->paidAmount);
            }

            $reservation = new Reservation($attributes);
            $reservation->number = static::nextNumber();
            $reservation->save();

            if ($reservation->status === ReservationStatus::Confirmed && $reservation->room_id !== null) {
                Room::query()->whereKey($reservation->room_id)->update([
                    'status' => RoomStatus::Reserved->value,
                ]);
            }

            return $reservation->fresh(['guest', 'room', 'bed', 'hotel', 'roomType']) ?? $reservation;
        });

        $this->dispatchCreatedWebhook($reservation);

        return $reservation;
    }

    protected function dispatchCreatedWebhook(Reservation $reservation): void
    {
        if (! class_exists(\App\Modules\Api\Services\WebhookDispatcher::class)) {
            return;
        }

        app(\App\Modules\Api\Services\WebhookDispatcher::class)->dispatch(
            'reservation.created',
            [
                'number' => $reservation->number,
                'status' => $reservation->status->value,
                'hotel_id' => $reservation->hotel_id,
                'guest_id' => $reservation->guest_id,
                'room_type_id' => $reservation->room_type_id,
                'check_in_date' => $reservation->check_in_date->toDateString(),
                'check_out_date' => $reservation->check_out_date->toDateString(),
                'booking_source' => $reservation->booking_source->value,
                'external_reference' => $reservation->external_reference,
                'total' => $reservation->total,
            ],
            $reservation->company_id,
        );
    }

    public static function nextNumber(): string
    {
        $prefix = 'RSV-'.now()->format('Ymd').'-';

        do {
            $number = $prefix.Str::upper(Str::random(4));
        } while (Reservation::query()->where('number', $number)->exists());

        return $number;
    }

    protected function estimateTotal(ReservationData $data, CarbonImmutable $checkIn, CarbonImmutable $checkOut): int
    {
        $nights = max(1, (int) $checkIn->diffInDays($checkOut));
        $nightly = 0;

        if ($data->bedId !== null) {
            $bed = Bed::query()->find($data->bedId);
            $nightly = $bed?->price ?? 0;
        } elseif ($data->roomId !== null) {
            $room = Room::query()->with('roomType')->find($data->roomId);
            $nightly = $room?->base_price ?? $room?->roomType?->base_price ?? 0;
        } elseif ($data->roomTypeId !== null) {
            $type = RoomType::query()->find($data->roomTypeId);
            $nightly = $type?->base_price ?? 0;
        }

        $subtotal = $nightly * $nights;
        $afterDiscount = max(0, $subtotal - $data->discount);

        return (int) ($afterDiscount + $data->tax);
    }
}
