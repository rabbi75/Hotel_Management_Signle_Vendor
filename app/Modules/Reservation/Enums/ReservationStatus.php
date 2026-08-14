<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum ReservationStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'info',
            self::CheckedIn => 'success',
            self::CheckedOut => 'neutral',
            self::Cancelled, self::NoShow => 'destructive',
        };
    }

    public function occupiesInventory(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::CheckedIn], true);
    }

    public function canCheckIn(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    public function canCheckOut(): bool
    {
        return $this === self::CheckedIn;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }
}
