<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum InvoiceStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';
    case Uncollectible = 'uncollectible';
    case Refunded = 'refunded';

    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Void, self::Refunded], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Open => 'warning',
            self::Draft => 'neutral',
            self::Void, self::Refunded => 'info',
            self::Uncollectible => 'destructive',
        };
    }
}
