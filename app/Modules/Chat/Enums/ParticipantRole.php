<?php

declare(strict_types=1);

namespace App\Modules\Chat\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * A participant's standing inside one conversation. Independent of workspace
 * role and of RBAC permissions: it only governs the room.
 */
enum ParticipantRole: string
{
    use HasLabel;

    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function color(): string
    {
        return match ($this) {
            self::Owner => 'primary',
            self::Admin => 'info',
            self::Member => 'neutral',
        };
    }

    public function canManageParticipants(): bool
    {
        return $this !== self::Member;
    }
}
