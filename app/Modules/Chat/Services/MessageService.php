<?php

declare(strict_types=1);

namespace App\Modules\Chat\Services;

use App\Modules\Chat\DTOs\MessageData;
use App\Modules\Chat\Events\MessageDeleted;
use App\Modules\Chat\Events\MessageSent;
use App\Modules\Chat\Events\MessageUpdated;
use App\Modules\Chat\Http\Resources\MessageResource;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writing, editing and reading the messages inside a conversation.
 *
 * Relations are eager-loaded on every path that produces a broadcast payload,
 * because the payload is serialised outside the request that created it and a
 * lazy load there would either throw under strict mode or fire N queries.
 */
class MessageService
{
    /**
     * @var list<string>
     */
    protected const PAYLOAD_RELATIONS = ['author', 'replyTo.author', 'attachments', 'reactions'];

    public function __construct(protected ConversationService $conversations) {}

    /**
     * @throws ValidationException When the body exceeds the configured ceiling.
     */
    public function send(Conversation $conversation, User $author, MessageData $data): Message
    {
        $this->guardLength($data->body);

        $message = DB::transaction(function () use ($conversation, $author, $data): Message {
            $message = new Message([
                ...$data->toAttributes(),
                'conversation_id' => $conversation->id,
                'user_id' => $author->id,
                // The reply target must live in this same room, or a reply
                // would quote a message the reader is not allowed to see.
                'reply_to_id' => $this->resolveReplyTarget($conversation, $data->replyToId),
            ]);
            $message->company_id = $conversation->company_id;
            $message->save();

            $conversation->last_message_at = $message->created_at;
            $conversation->save();

            return $message;
        });

        $this->conversations->forgetUnreadForConversation($conversation);

        MessageSent::dispatch($conversation->id, $this->payload($message));

        return $message;
    }

    public function edit(Message $message, string $body): Message
    {
        $this->guardLength($body);

        $message->body = $body;
        $message->edited_at = now();
        $message->save();

        MessageUpdated::dispatch($message->conversation_id, $this->payload($message));

        return $message;
    }

    public function delete(Message $message): void
    {
        $conversationId = $message->conversation_id;
        $conversation = $message->relationLoaded('conversation') ? $message->conversation : $message->conversation()->first();

        $message->delete();

        if ($conversation instanceof Conversation) {
            $this->conversations->forgetUnreadForConversation($conversation);
        }

        MessageDeleted::dispatch($conversationId, $message->id);
    }

    /**
     * Re-broadcast a message whose reactions changed; the shape a subscriber
     * receives is identical to an edit.
     */
    public function broadcastUpdate(Message $message): void
    {
        MessageUpdated::dispatch($message->conversation_id, $this->payload($message));
    }

    /**
     * Thread history, newest first.
     *
     * Cursor based rather than offset: a thread grows at the end while the user
     * is scrolling, and an offset page would silently repeat or skip rows.
     *
     * @return CursorPaginator<int, Message>
     */
    public function history(Conversation $conversation, ?string $cursor = null): CursorPaginator
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->with(self::PAYLOAD_RELATIONS)
            ->orderByDesc('id')
            ->cursorPaginate(
                max(1, (int) config('saas.chat.messages_per_page')),
                ['*'],
                'cursor',
                $cursor,
            );
    }

    /**
     * Full-text-ish search across the rooms a user belongs to, or inside one.
     *
     * @return CursorPaginator<int, Message>
     */
    public function search(User $user, string $term, ?Conversation $conversation = null, ?string $cursor = null): CursorPaginator
    {
        return Message::query()
            ->with([...self::PAYLOAD_RELATIONS, 'conversation.participants.user'])
            ->where('body', 'like', '%'.$term.'%')
            ->when(
                $conversation instanceof Conversation,
                static fn (Builder $query): Builder => $query->where('conversation_id', $conversation?->id),
                static fn (Builder $query): Builder => $query->whereHas(
                    'conversation.participants',
                    static fn (Builder $participants): Builder => $participants->where('user_id', $user->id),
                ),
            )
            ->orderByDesc('id')
            ->cursorPaginate(
                max(1, (int) config('saas.chat.messages_per_page')),
                ['*'],
                'cursor',
                $cursor,
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Message $message): array
    {
        return (new MessageResource($message->load(self::PAYLOAD_RELATIONS)))->resolve();
    }

    /**
     * @throws ValidationException
     */
    protected function guardLength(string $body): void
    {
        $max = max(1, (int) config('saas.chat.max_message_length'));

        if (mb_strlen($body) > $max) {
            throw ValidationException::withMessages([
                'body' => __('Messages may not be longer than :max characters.', ['max' => $max]),
            ]);
        }
    }

    protected function resolveReplyTarget(Conversation $conversation, ?int $replyToId): ?int
    {
        if ($replyToId === null) {
            return null;
        }

        $exists = Message::query()
            ->whereKey($replyToId)
            ->where('conversation_id', $conversation->id)
            ->exists();

        return $exists ? $replyToId : null;
    }
}
