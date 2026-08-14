<?php

declare(strict_types=1);

namespace App\Modules\Chat\DTOs;

use App\Modules\Chat\Enums\MessageType;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

/**
 * The body of a message as submitted by a client.
 *
 * There is no update variant: an edit may only ever change the body, so there
 * is no field whose absence could be mistaken for a deliberate clear.
 */
readonly class MessageData extends Data
{
    public function __construct(
        public string $body,
        public MessageType $type = MessageType::Text,
        public ?int $replyToId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $replyTo = $request->input('reply_to_id');

        return new self(
            body: (string) $request->string('body'),
            type: MessageType::tryFrom((string) $request->string('type')) ?? MessageType::Text,
            replyToId: is_numeric($replyTo) ? (int) $replyTo : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'body' => $this->body,
            'type' => $this->type,
            'reply_to_id' => $this->replyToId,
        ];
    }
}
