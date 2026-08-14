<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\DTOs;

use App\Modules\Reservation\Enums\BookingSource;
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
    ) {}
}
