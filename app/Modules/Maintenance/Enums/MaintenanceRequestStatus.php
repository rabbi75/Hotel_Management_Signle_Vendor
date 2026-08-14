<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum MaintenanceRequestStatus: string
{
    use HasLabel;

    case Open = 'open';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::OnHold => 'neutral',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::InProgress, self::OnHold], true);
    }

    public function blocksInventory(): bool
    {
        return in_array($this, [self::Open, self::InProgress], true);
    }
}
