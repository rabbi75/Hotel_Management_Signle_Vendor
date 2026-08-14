<?php

declare(strict_types=1);

namespace App\Modules\Audit\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum Severity: string
{
    use HasLabel;

    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Critical = 'critical';

    public function color(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Notice => 'neutral',
            self::Warning => 'warning',
            self::Critical => 'danger',
        };
    }
}
