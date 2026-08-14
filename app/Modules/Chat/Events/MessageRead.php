<?php

declare(strict_types=1);

namespace App\Modules\Chat\Events;

use Carbon\CarbonInterface;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A participant advanced their read marker. Everyone else in the room uses it
 * to draw read receipts.
 */
class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $userId,
        public readonly CarbonInterface $readAt,
        public readonly ?int $lastMessageId = null,
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
        return 'message.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'read_at' => $this->readAt->toIso8601String(),
            'last_message_id' => $this->lastMessageId,
        ];
    }
}
