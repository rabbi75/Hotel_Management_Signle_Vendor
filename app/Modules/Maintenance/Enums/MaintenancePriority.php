<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum MaintenancePriority: string
{
    use HasLabel;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function color(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Normal => 'info',
            self::High => 'warning',
            self::Urgent => 'destructive',
        };
    }
}
