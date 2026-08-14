<?php

declare(strict_types=1);

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Enums\ConversationType;
use App\Modules\Chat\Enums\ParticipantRole;
use App\Modules\Chat\Events\MessageRead;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\Chat\Models\Message;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything that happens to a conversation as a container: who is in it, what
 * each participant has read, and how many messages they have yet to see.
 *
 * Unread counts are cached because the sidebar badge and the navigation badge
 * both read them on every request; every write path here busts the cache so the
 * badge can never lag behind an action the user just took.
 */
class ConversationService
{
    public function __construct(protected CacheRepository $cache) {}

    /**
     * The one direct conversation between two users, creating it if needed.
     *
     * Race-safe by construction: the unique index on (company_id, direct_key)
     * is the arbiter, and a losing insert simply re-reads the winner's row. An
     * application-level "check then insert" would let two simultaneous opens
     * create two DMs between the same pair.
     */
    public function findOrCreateDirect(User $user, User $other): Conversation
    {
        $key = Conversation::directKeyFor($user->id, $other->id);

        $existing = Conversation::query()->where('direct_key', $key)->first();

        if ($existing instanceof Conversation) {
            return $existing;
        }

        try {
            $conversation = DB::transaction(function () use ($user, $other, $key): Conversation {
                $conversation = new Conversation([
                    'created_by' => $user->id,
                    'type' => ConversationType::Direct,
                    'name' => null,
                    'description' => null,
                    'direct_key' => $key,
                ]);
                $conversation->save();

                $this->attach($conversation, [$user->id, $other->id], ParticipantRole::Member);

                return $conversation;
            });
        } catch (QueryException) {
            // Lost the race: the other request already inserted this pair.
            $conversation = Conversation::query()->where('direct_key', $key)->firstOrFail();
        }

        $this->forgetUnread($user);
        $this->forgetUnread($other);

        return $conversation;
    }

    /**
     * @param  list<int>  $participantIds  Members to add alongside the creator.
     */
    public function createGroup(
        User $creator,
        string $name,
        ?string $description,
        array $participantIds,
        ConversationType $type = ConversationType::Group,
    ): Conversation {
        return DB::transaction(function () use ($creator, $name, $description, $participantIds, $type): Conversation {
            $conversation = new Conversation([
                'created_by' => $creator->id,
                'type' => $type,
                'name' => $name,
                'description' => $description,
                'direct_key' => null,
            ]);
            $conversation->save();

            $this->attach($conversation, [$creator->id], ParticipantRole::Owner);
            $this->attach($conversation, array_values(array_diff($participantIds, [$creator->id])), ParticipantRole::Member);

            return $conversation;
        });
    }

    /**
     * @param  list<int>  $userIds
     */
    public function addParticipants(Conversation $conversation, array $userIds): void
    {
        $this->attach($conversation, $userIds, ParticipantRole::Member);
    }

    public function removeParticipant(Conversation $conversation, int $userId): void
    {
        $conversation->participants()->where('user_id', $userId)->delete();

        $this->forgetUnreadFor($conversation->company_id, $userId);
    }

    /**
     * Advance a participant's read marker to now and tell the room.
     */
    public function markRead(Conversation $conversation, User $user): void
    {
        $participant = $conversation->participantFor($user->id);

        if (! $participant instanceof ConversationParticipant) {
            return;
        }

        $readAt = now();

        $participant->last_read_at = $readAt;
        $participant->save();

        $this->forgetUnread($user);

        $lastMessageId = $conversation->messages()->max('id');

        MessageRead::dispatch(
            $conversation->id,
            $user->id,
            $readAt,
            is_numeric($lastMessageId) ? (int) $lastMessageId : null,
        );
    }

    /**
     * Unread counts for every conversation the user takes part in.
     *
     * @return array<int, int> Conversation id => count.
     */
    public function unreadCounts(User $user): array
    {
        /** @var array<int, int> $counts */
        $counts = $this->cache->remember(
            $this->cacheKey(current_company_id(), $user->id),
            (int) config('saas.notifications.unread_cache_ttl', 300),
            fn (): array => $this->computeUnreadCounts($user),
        );

        return $counts;
    }

    public function totalUnread(User $user): int
    {
        return array_sum($this->unreadCounts($user));
    }

    public function forgetUnread(User $user): void
    {
        $this->forgetUnreadFor(current_company_id(), $user->id);
    }

    /**
     * Bust every participant's cached counts — used after a message lands.
     */
    public function forgetUnreadForConversation(Conversation $conversation): void
    {
        $ids = $conversation->participants()->pluck('user_id');

        foreach ($ids as $id) {
            $this->forgetUnreadFor($conversation->company_id, (int) $id);
        }
    }

    /**
     * The conversations a user can see, ordered by recency of activity.
     *
     * @return Collection<int, Conversation>
     */
    public function listFor(User $user, ?string $search = null): Collection
    {
        return Conversation::query()
            ->forParticipant($user->id)
            ->with(['participants.user', 'latestMessage.author'])
            ->when(
                $search !== null && $search !== '',
                static fn (Builder $query): Builder => $query->where(static function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhereHas('users', static fn (Builder $users) => $users->where('users.name', 'like', "%{$search}%"));
                }),
            )
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->get();
    }

    /**
     * @param  list<int>  $userIds
     */
    protected function attach(Conversation $conversation, array $userIds, ParticipantRole $role): void
    {
        foreach (array_unique($userIds) as $userId) {
            ConversationParticipant::query()->firstOrCreate(
                ['conversation_id' => $conversation->id, 'user_id' => $userId],
                ['role' => $role, 'joined_at' => now(), 'last_read_at' => null, 'muted_at' => null],
            );

            $this->forgetUnreadFor($conversation->company_id, $userId);
        }
    }

    /**
     * @return array<int, int>
     */
    protected function computeUnreadCounts(User $user): array
    {
        /** @var array<int, int> $counts */
        $counts = [];

        $participations = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->get();

        foreach ($participations as $participation) {
            $count = Message::query()
                ->where('conversation_id', $participation->conversation_id)
                ->where('user_id', '!=', $user->id)
                ->when(
                    $participation->last_read_at !== null,
                    static fn (Builder $query): Builder => $query->where('created_at', '>', $participation->last_read_at),
                )
                ->count();

            if ($count > 0) {
                $counts[$participation->conversation_id] = $count;
            }
        }

        return $counts;
    }

    protected function forgetUnreadFor(?int $companyId, int $userId): void
    {
        $this->cache->forget($this->cacheKey($companyId, $userId));

        // The sidebar badge is rendered from the same number and lives inside
        // the per-user navigation cache, so that has to go too — otherwise the
        // badge keeps showing a count the conversation list no longer agrees
        // with, for up to saas.cache.navigation_ttl.
        /** @var array<string, mixed> $locales */
        $locales = (array) config('saas.locales', []);

        foreach (array_keys($locales) as $locale) {
            $this->cache->forget("navigation:{$userId}:{$locale}");
        }
    }

    protected function cacheKey(?int $companyId, int $userId): string
    {
        return 'chat:'.($companyId ?? 0).":{$userId}:unread";
    }
}
