<?php

declare(strict_types=1);

namespace App\Modules\Notification\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a freshly stored notification to the recipient's open tabs.
 *
 * The unread count travels with it so the bell badge never has to make a
 * follow-up request just to learn it went from 3 to 4.
 */
class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $notification  A serialised NotificationResource.
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $notification,
        public readonly int $unreadCount,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
            'unread_count' => $this->unreadCount,
        ];
    }
}
