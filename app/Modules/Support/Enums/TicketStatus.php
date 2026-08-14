<?php

declare(strict_types=1);

namespace App\Modules\Support\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum TicketStatus: string
{
    use HasLabel;

    case Open = 'open';
    case AwaitingSupport = 'awaiting_support';
    case AwaitingCustomer = 'awaiting_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function color(): string
    {
        return match ($this) {
            self::Open, self::AwaitingSupport => 'warning',
            self::AwaitingCustomer => 'info',
            self::Resolved => 'success',
            self::Closed => 'neutral',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::AwaitingSupport, self::AwaitingCustomer], true);
    }
}
