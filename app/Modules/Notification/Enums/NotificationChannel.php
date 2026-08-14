<?php

declare(strict_types=1);

namespace App\Modules\Notification\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * Delivery channels a user can opt in and out of.
 *
 * The values match Laravel's channel names exactly so a case can be handed
 * straight to `via()` without translation.
 */
enum NotificationChannel: string
{
    use HasLabel;

    case Database = 'database';
    case Mail = 'mail';
    case Broadcast = 'broadcast';

    /**
     * Channels enabled for this installation, in the order config declares them.
     *
     * @return list<self>
     */
    public static function enabled(): array
    {
        /** @var list<string> $configured */
        $configured = config('saas.notifications.channels', []);

        return array_values(array_filter(array_map(
            static fn (string $channel): ?self => self::tryFrom($channel),
            $configured,
        )));
    }

    /**
     * Dot path into `users.preferences` holding this channel's opt-in flag.
     */
    public function preferenceKey(): string
    {
        return "notifications.channels.{$this->value}";
    }

    /**
     * The in-app inbox is not opt-out: it is the record of what was sent, and
     * a user who disabled it would have no way to see they missed something.
     */
    public function isOptional(): bool
    {
        return $this !== self::Database;
    }
}
