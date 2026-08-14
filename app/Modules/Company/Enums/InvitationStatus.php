<?php

declare(strict_types=1);

namespace App\Modules\Company\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum InvitationStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Revoked, self::Expired => 'neutral',
        };
    }
}
