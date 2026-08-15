<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\DTOs;

use App\Modules\OnlineBooking\Enums\PaymentStatus;
use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Support\DTOs\Data;

readonly class OnlineBookingData extends Data
{
    public function __construct(
        public int $hotelId,
        public int $roomTypeId,
        public string $checkInDate,
        public string $checkOutDate,
        public string $guestFirstName,
        public string $guestLastName,
        public ?string $guestEmail = null,
        public ?string $guestPhone = null,
        public int $adults = 1,
        public int $children = 0,
        public ?string $specialRequests = null,
        public ?string $externalReference = null,
        public array $channelMetadata = [],
        public BookingSource $bookingSource = BookingSource::Website,
        public ReservationStatus $status = ReservationStatus::Confirmed,
        public ?int $customerId = null,
        public ?int $paymentMethodId = null,
        public PaymentStatus $paymentStatus = PaymentStatus::Unpaid,
        public ?string $paymentReference = null,
        public int $paidAmount = 0,
        public ?string $paidAt = null,
    ) {}
}
