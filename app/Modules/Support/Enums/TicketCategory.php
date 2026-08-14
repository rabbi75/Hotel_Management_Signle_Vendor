<?php

declare(strict_types=1);

namespace App\Modules\Support\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum TicketCategory: string
{
    use HasLabel;

    case Billing = 'billing';
    case Technical = 'technical';
    case Account = 'account';
    case Other = 'other';
}
