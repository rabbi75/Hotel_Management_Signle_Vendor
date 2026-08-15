<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum PaymentStatus: string
{
    use HasLabel;

    case Unpaid = 'unpaid';
    case Awaiting = 'awaiting';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Awaiting => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Awaiting => 'info',
            self::Paid => 'success',
            self::Failed => 'destructive',
        };
    }
}
