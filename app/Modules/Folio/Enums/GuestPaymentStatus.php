<?php

declare(strict_types=1);

namespace App\Modules\Folio\Enums;

enum GuestPaymentStatus: string
{
    case Completed = 'completed';
    case Pending = 'pending';
    case Refunded = 'refunded';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Completed => __('Completed'),
            self::Pending => __('Pending'),
            self::Refunded => __('Refunded'),
            self::Void => __('Void'),
        };
    }

    public function countsTowardPaid(): bool
    {
        return $this === self::Completed;
    }
}
