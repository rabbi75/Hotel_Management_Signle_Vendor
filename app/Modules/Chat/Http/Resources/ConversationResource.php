<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\Chat\Models\Message;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * Unread counts are per-viewer, so they are attached by the service rather
     * than derived here; `withUnread()` carries them into the payload.
     *
     * @var array<int, int>
     */
    protected array $unread = [];

    protected ?int $viewerId = null;

    /**
     * @param  array<int, int>  $unread  Conversation id => unread message count.
     */
    public function withUnread(array $unread, ?int $viewerId): self
    {
        $this->unread = $unread;
        $this->viewerId = $viewerId;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Conversation $conversation */
        $conversation = $this->resource;

        $viewerId = $this->viewerId ?? $request->user()?->getAuthIdentifier();
        $viewerId = is_numeric($viewerId) ? (int) $viewerId : null;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'type_label' => $conversation->type->label(),
            'name' => $conversation->name,
            'title' => $conversation->titleFor($viewerId),
            'description' => $conversation->description,
            'created_by' => $conversation->created_by,
            'participants' => $this->participants($request, $conversation),
            'participant_ids' => $this->participantIds($conversation),
            'last_message' => $this->lastMessage($conversation),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => $this->unread[$conversation->id] ?? 0,
            'muted' => $this->viewerParticipant($conversation, $viewerId)?->isMuted() ?? false,
            'created_at' => $conversation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function participants(Request $request, Conversation $conversation): array
    {
        if (! $conversation->relationLoaded('participants')) {
            return [];
        }

        return ParticipantResource::collection($conversation->participants)->resolve($request);
    }

    /**
     * @return list<int>
     */
    protected function participantIds(Conversation $conversation): array
    {
        if (! $conversation->relationLoaded('participants')) {
            return [];
        }

        return array_values(array_map(
            static fn (ConversationParticipant $participant): int => $participant->user_id,
            $conversation->participants->all(),
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function lastMessage(Conversation $conversation): ?array
    {
        if (! $conversation->relationLoaded('latestMessage')) {
            return null;
        }

        $message = $conversation->latestMessage;

        if (! $message instanceof Message) {
            return null;
        }

        $author = $message->relationLoaded('author') ? $message->author : null;

        return [
            'id' => $message->id,
            'body' => Str::limit($message->body, 120),
            'author' => $author instanceof User ? $author->name : null,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    protected function viewerParticipant(Conversation $conversation, ?int $viewerId): ?ConversationParticipant
    {
        if ($viewerId === null || ! $conversation->relationLoaded('participants')) {
            return null;
        }

        return $conversation->participants
            ->first(static fn (ConversationParticipant $participant): bool => $participant->user_id === $viewerId);
    }
}
