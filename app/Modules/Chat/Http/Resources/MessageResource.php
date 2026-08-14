<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Models\MessageAttachment;
use App\Modules\Chat\Models\MessageReaction;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Message $message */
        $message = $this->resource;

        $author = $message->relationLoaded('author') ? $message->author : null;

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'type' => $message->type->value,
            'body' => $message->body,
            'author' => $author instanceof User ? self::author($author) : null,
            'reply_to' => $this->replyTo($message),
            'attachments' => $this->attachments($message),
            'reactions' => $this->reactions($message),
            'edited' => $message->edited_at !== null,
            'edited_at' => $message->edited_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function author(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'initials' => $user->initials(),
            'avatar' => $user->avatarUrl(),
        ];
    }

    /**
     * The quoted parent, trimmed: the thread only renders a one-line preview,
     * and sending the whole parent body would double the payload of a reply.
     *
     * @return array<string, mixed>|null
     */
    protected function replyTo(Message $message): ?array
    {
        if (! $message->relationLoaded('replyTo')) {
            return null;
        }

        $parent = $message->replyTo;

        if (! $parent instanceof Message) {
            return null;
        }

        $parentAuthor = $parent->relationLoaded('author') ? $parent->author : null;

        return [
            'id' => $parent->id,
            'body' => Str::limit($parent->body, 140),
            'author' => $parentAuthor instanceof User ? $parentAuthor->name : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function attachments(Message $message): array
    {
        if (! $message->relationLoaded('attachments')) {
            return [];
        }

        return array_values(array_map(
            static fn (MessageAttachment $attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'is_image' => $attachment->isImage(),
                'url' => $attachment->url(),
                'thumbnail' => $attachment->thumbnailUrl(),
            ],
            $message->attachments->all(),
        ));
    }

    /**
     * Reactions collapsed to one row per emoji, with the reactor ids so the UI
     * can highlight the viewer's own choice without another lookup.
     *
     * @return list<array<string, mixed>>
     */
    protected function reactions(Message $message): array
    {
        if (! $message->relationLoaded('reactions')) {
            return [];
        }

        $grouped = [];

        foreach ($message->reactions as $reaction) {
            /** @var MessageReaction $reaction */
            $grouped[$reaction->emoji] ??= ['emoji' => $reaction->emoji, 'count' => 0, 'user_ids' => []];
            $grouped[$reaction->emoji]['count']++;
            $grouped[$reaction->emoji]['user_ids'][] = $reaction->user_id;
        }

        return array_values($grouped);
    }
}
