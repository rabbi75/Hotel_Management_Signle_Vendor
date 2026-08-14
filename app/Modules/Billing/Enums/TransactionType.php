<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum TransactionType: string
{
    use HasLabel;

    case Charge = 'charge';
    case Refund = 'refund';

    public function color(): string
    {
        return $this === self::Charge ? 'success' : 'info';
    }
}
