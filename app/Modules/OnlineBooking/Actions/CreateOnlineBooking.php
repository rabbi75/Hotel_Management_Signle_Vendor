<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Actions;

use App\Modules\Guest\Actions\CreateGuest;
use App\Modules\Guest\DTOs\GuestData;
use App\Modules\Guest\Models\Guest;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\OnlineBooking\DTOs\OnlineBookingData;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\OnlineBooking\Services\AvailabilityQueryService;
use App\Modules\OnlineBooking\Services\RateQuoteService;
use App\Modules\Reservation\Actions\CreateReservation;
use App\Modules\Reservation\DTOs\ReservationData;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOnlineBooking
{
    public function __construct(
        protected AvailabilityQueryService $availability,
        protected RateQuoteService $rates,
        protected CreateGuest $createGuest,
        protected CreateReservation $createReservation,
    ) {}

    public function handle(OnlineBookingData $data): Reservation
    {
        return DB::transaction(function () use ($data): Reservation {
            $hotel = Hotel::query()->whereKey($data->hotelId)->firstOrFail();

            $setting = BookingSetting::query()
                ->where('hotel_id', $hotel->id)
                ->where('is_enabled', true)
                ->first();

            if (! $setting instanceof BookingSetting) {
                throw ValidationException::withMessages([
                    'hotel_id' => __('Online booking is not enabled for this property.'),
                ]);
            }

            $checkIn = CarbonImmutable::parse($data->checkInDate)->startOfDay();
            $checkOut = CarbonImmutable::parse($data->checkOutDate)->startOfDay();

            $this->assertAdvanceWindow($setting, $checkIn);

            if ($data->externalReference !== null) {
                $exists = Reservation::query()
                    ->where('external_reference', $data->externalReference)
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'external_reference' => __('A booking with this external reference already exists.'),
                    ]);
                }
            }

            $roomType = RoomType::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($data->roomTypeId)
                ->where('is_active', true)
                ->first();

            if (! $roomType instanceof RoomType) {
                throw ValidationException::withMessages([
                    'room_type_id' => __('The selected room type is not available.'),
                ]);
            }

            if ($this->availability->availableCount($hotel->id, $roomType->id, $checkIn, $checkOut) < 1) {
                throw ValidationException::withMessages([
                    'room_type_id' => __('No rooms are available for the selected dates.'),
                ]);
            }

            $guest = $this->resolveGuest($data, $hotel->id);
            $quote = $this->rates->quote($hotel, $roomType, $checkIn, $checkOut);

            return $this->createReservation->handle(new ReservationData(
                hotelId: $hotel->id,
                guestId: $guest->id,
                checkInDate: $checkIn->toDateString(),
                checkOutDate: $checkOut->toDateString(),
                roomTypeId: $roomType->id,
                adults: $data->adults,
                children: $data->children,
                bookingSource: $data->bookingSource,
                specialRequests: $data->specialRequests,
                total: (int) $quote['total'],
                paidAmount: $data->paidAmount,
                status: $data->status,
                externalReference: $data->externalReference,
                channelMetadata: $data->channelMetadata,
                customerId: $data->customerId,
                paymentMethodId: $data->paymentMethodId,
                paymentStatus: $data->paymentStatus,
                paymentReference: $data->paymentReference,
                paidAt: $data->paidAt,
            ));
        });
    }

    protected function resolveGuest(OnlineBookingData $data, int $hotelId): Guest
    {
        if ($data->guestEmail !== null) {
            $existing = Guest::query()
                ->where('hotel_id', $hotelId)
                ->where('email', $data->guestEmail)
                ->first();

            if ($existing instanceof Guest) {
                return $existing;
            }
        }

        return $this->createGuest->handle(new GuestData(
            firstName: $data->guestFirstName,
            lastName: $data->guestLastName,
            hotelId: $hotelId,
            email: $data->guestEmail,
            phone: $data->guestPhone,
        ));
    }

    protected function assertAdvanceWindow(BookingSetting $setting, CarbonImmutable $checkIn): void
    {
        $today = CarbonImmutable::today();
        $daysAhead = $today->diffInDays($checkIn, false);

        if ($daysAhead < $setting->min_advance_days) {
            throw ValidationException::withMessages([
                'check_in_date' => __('Bookings must be made at least :days day(s) in advance.', [
                    'days' => $setting->min_advance_days,
                ]),
            ]);
        }

        if ($daysAhead > $setting->max_advance_days) {
            throw ValidationException::withMessages([
                'check_in_date' => __('Bookings cannot be made more than :days day(s) in advance.', [
                    'days' => $setting->max_advance_days,
                ]),
            ]);
        }
    }
}
