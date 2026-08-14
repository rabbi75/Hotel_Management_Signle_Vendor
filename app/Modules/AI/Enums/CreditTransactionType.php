<?php

declare(strict_types=1);

namespace App\Modules\AI\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum CreditTransactionType: string
{
    use HasLabel;

    case Reservation = 'reservation';
    case Charge = 'charge';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Allowance = 'allowance';

    public function color(): string
    {
        return match ($this) {
            self::Charge => 'warning',
            self::Refund, self::Allowance => 'success',
            self::Adjustment => 'info',
            self::Reservation => 'muted',
        };
    }
}
