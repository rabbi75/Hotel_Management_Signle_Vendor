<?php

declare(strict_types=1);

namespace App\Modules\Chat\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * What kind of room a conversation is.
 *
 * `direct` is special-cased throughout: it always has exactly two participants
 * and is deduplicated per pair, so a name is derived rather than stored.
 */
enum ConversationType: string
{
    use HasLabel;

    case Direct = 'direct';
    case Group = 'group';
    case Channel = 'channel';

    public function color(): string
    {
        return match ($this) {
            self::Direct => 'neutral',
            self::Group => 'info',
            self::Channel => 'primary',
        };
    }

    public function requiresName(): bool
    {
        return $this !== self::Direct;
    }
}
