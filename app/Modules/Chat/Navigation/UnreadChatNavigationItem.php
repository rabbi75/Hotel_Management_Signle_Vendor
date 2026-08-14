<?php

declare(strict_types=1);

namespace App\Modules\Chat\Navigation;

use App\Modules\Chat\Services\ConversationService;
use App\Modules\User\Models\User;
use App\Support\Navigation\NavigationItem;

/**
 * The Chat sidebar entry, carrying the viewer's unread total as its badge.
 *
 * The badge has to be resolved against the user rather than set at boot, since
 * modules register their navigation long before a request has an authenticated
 * user. {@see ConversationService::forgetUnread()} evicts the navigation cache
 * alongside the counter so the badge cannot outlive the count it shows.
 */
class UnreadChatNavigationItem extends NavigationItem
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(User $user): array
    {
        $unread = app(ConversationService::class)->totalUnread($user);

        return [
            ...parent::toArray($user),
            'badge' => $unread > 0 ? (string) min($unread, 99) : null,
        ];
    }
}
