<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum TransactionStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function color(): string
    {
        return match ($this) {
            self::Succeeded => 'success',
            self::Pending => 'warning',
            self::Failed => 'destructive',
            self::Refunded => 'info',
        };
    }
}
