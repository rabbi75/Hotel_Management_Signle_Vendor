<?php

declare(strict_types=1);

namespace App\Modules\Api\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum WebhookDeliveryStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Retrying = 'retrying';

    public function color(): string
    {
        return match ($this) {
            self::Delivered => 'success',
            self::Failed => 'destructive',
            self::Retrying => 'warning',
            self::Pending => 'muted',
        };
    }
}
