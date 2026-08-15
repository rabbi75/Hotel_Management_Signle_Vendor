<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Actions;

use App\Modules\HotelOperations\Events\WebsiteBookingRequested;
use App\Modules\OnlineBooking\DTOs\OnlineBookingData;
use App\Modules\OnlineBooking\Enums\PaymentStatus;
use App\Modules\OnlineBooking\Models\BookingPaymentMethod;
use App\Modules\OnlineBooking\Models\Customer;
use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Validation\ValidationException;

class PlacePublicOrder
{
    public function __construct(protected CreateOnlineBooking $createBooking) {}

    /**
     * @param  array<string, mixed>  $cart
     */
    public function handle(
        array $cart,
        BookingPaymentMethod $method,
        ?Customer $customer,
        ?string $paymentReference = null,
        bool $prepaid = false,
    ): Reservation {
        if (! $method->is_enabled) {
            throw ValidationException::withMessages([
                'payment_method_id' => __('This payment method is not available.'),
            ]);
        }

        if ($method->requiresPrepaid() && ! $prepaid) {
            throw ValidationException::withMessages([
                'payment_method_id' => __('Complete payment before placing this order.'),
            ]);
        }

        $paid = $prepaid;

        $reservation = $this->createBooking->handle(new OnlineBookingData(
            hotelId: (int) $cart['hotel_id'],
            roomTypeId: (int) $cart['room_type_id'],
            checkInDate: (string) $cart['check_in_date'],
            checkOutDate: (string) $cart['check_out_date'],
            guestFirstName: (string) $cart['first_name'],
            guestLastName: (string) $cart['last_name'],
            guestEmail: (string) $cart['email'],
            guestPhone: isset($cart['phone']) && $cart['phone'] !== '' ? (string) $cart['phone'] : null,
            adults: (int) ($cart['adults'] ?? 1),
            children: (int) ($cart['children'] ?? 0),
            specialRequests: isset($cart['special_requests']) && $cart['special_requests'] !== ''
                ? (string) $cart['special_requests']
                : null,
            bookingSource: BookingSource::Website,
            status: ReservationStatus::Pending,
            customerId: $customer?->id,
            paymentMethodId: $method->id,
            paymentStatus: $paid ? PaymentStatus::Paid : PaymentStatus::Unpaid,
            paymentReference: $paymentReference,
            paidAmount: 0,
            paidAt: $paid ? now()->toDateTimeString() : null,
        ));

        if ($paid) {
            $reservation->forceFill([
                'paid_amount' => $reservation->total,
                'due_amount' => 0,
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => now(),
            ])->save();
        }

        WebsiteBookingRequested::dispatch($reservation->fresh() ?? $reservation);

        return $reservation->fresh(['guest', 'roomType', 'paymentMethod']) ?? $reservation;
    }
}
