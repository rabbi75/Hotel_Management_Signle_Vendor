<?php

declare(strict_types=1);

namespace App\Modules\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast synchronously on purpose: a typing indicator that arrives after a
 * queue round trip has already expired, so queueing it would be worse than not
 * sending it at all.
 *
 * The TTL travels with the event so the client expires the indicator on the
 * same clock the server configured, without polling for a "stopped" event.
 */
class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $userId,
        public readonly string $userName,
        public readonly bool $typing = true,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'typing' => $this->typing,
            'ttl' => (int) config('saas.chat.typing_ttl'),
        ];
    }
}
