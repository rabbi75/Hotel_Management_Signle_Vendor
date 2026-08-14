<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum BookingSource: string
{
    use HasLabel;

    case WalkIn = 'walk_in';
    case Website = 'website';
    case Phone = 'phone';
    case Email = 'email';
    case BookingCom = 'booking_com';
    case Expedia = 'expedia';
    case Agoda = 'agoda';
    case Airbnb = 'airbnb';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
            self::Website => 'Website',
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::BookingCom => 'Booking.com',
            self::Expedia => 'Expedia',
            self::Agoda => 'Agoda',
            self::Airbnb => 'Airbnb',
            self::Other => 'Other',
        };
    }
}
