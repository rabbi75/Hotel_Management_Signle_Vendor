<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum HousekeepingTaskStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::InProgress], true);
    }

    public function canStart(): bool
    {
        return $this === self::Pending;
    }

    public function canComplete(): bool
    {
        return $this === self::InProgress;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Pending, self::InProgress], true);
    }
}
