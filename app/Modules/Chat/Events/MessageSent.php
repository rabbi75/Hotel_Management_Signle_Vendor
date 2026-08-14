<?php

declare(strict_types=1);

namespace App\Modules\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new message landed in a conversation.
 *
 * The serialised message travels with the event so an open thread can append
 * it without a follow-up fetch.
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $message  A serialised MessageResource.
     */
    public function __construct(
        public readonly int $conversationId,
        public readonly array $message,
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
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
