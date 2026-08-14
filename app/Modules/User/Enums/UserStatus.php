<?php

declare(strict_types=1);

namespace App\Modules\User\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum UserStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Invited => 'info',
            self::Suspended => 'danger',
        };
    }

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
