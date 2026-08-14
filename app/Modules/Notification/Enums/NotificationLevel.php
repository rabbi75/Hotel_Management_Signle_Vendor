<?php

declare(strict_types=1);

namespace App\Modules\Notification\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * Severity of a user-facing notification. Drives the bell icon colour, the mail
 * template's accent and whether the toast auto-dismisses.
 */
enum NotificationLevel: string
{
    use HasLabel;

    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Critical = 'critical';

    public function color(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Success => 'success',
            self::Warning => 'warning',
            self::Critical => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Success => 'circle-check',
            self::Warning => 'triangle-alert',
            self::Critical => 'octagon-alert',
        };
    }

    /**
     * Laravel's mail template only understands these three themes.
     */
    public function mailTheme(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Warning, self::Critical => 'error',
            self::Info => 'default',
        };
    }
}
