<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Http\Resources\NotificationResource;
use App\Modules\User\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Everything the UI does with a user's notification inbox.
 *
 * The unread counter is cached because it is serialised into *every* Inertia
 * response; every mutation here busts it, so the badge can never lag behind
 * an action the user just took.
 */
class NotificationCenter
{
    public function __construct(protected CacheRepository $cache) {}

    /**
     * The bell dropdown: the unread count plus the newest few notifications.
     *
     * @return array{unread: int, items: list<array<string, mixed>>}
     */
    public function preview(User $user): array
    {
        $limit = max(1, (int) config('saas.notifications.bell_preview_count'));

        $items = $user->notifications()
            ->latest()
            ->limit($limit)
            ->get();

        return [
            'unread' => $this->unreadCount($user),
            'items' => array_values(array_map(
                static fn (DatabaseNotification $notification): array => (new NotificationResource($notification))->resolve(),
                $items->all(),
            )),
        ];
    }

    /**
     * The full inbox screen, as a server-side data table.
     *
     * @return array<string, mixed>
     */
    public function paginate(User $user, Request $request): array
    {
        return $this->table($user, $request)
            ->transform(static fn (DatabaseNotification $notification): array => (new NotificationResource($notification))->resolve())
            ->toArray();
    }

    /**
     * @param  list<string>  $ids
     */
    public function markAsRead(User $user, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $count = $user->unreadNotifications()
            ->whereIn('id', $ids)
            ->update(['read_at' => now()]);

        $this->forgetUnreadCount($user);

        return $count;
    }

    public function markAllAsRead(User $user): int
    {
        $count = $user->unreadNotifications()->update(['read_at' => now()]);

        $this->forgetUnreadCount($user);

        return $count;
    }

    /**
     * @param  list<string>  $ids
     */
    public function delete(User $user, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $count = $user->notifications()->whereIn('id', $ids)->delete();

        $this->forgetUnreadCount($user);

        return $count;
    }

    public function deleteAll(User $user, bool $readOnly = false): int
    {
        $query = $user->notifications();

        if ($readOnly) {
            $query->whereNotNull('read_at');
        }

        $count = $query->delete();

        $this->forgetUnreadCount($user);

        return $count;
    }

    public function unreadCount(User $user): int
    {
        return (int) $this->cache->remember(
            $this->unreadCacheKey($user),
            (int) config('saas.notifications.unread_cache_ttl'),
            static fn (): int => $user->unreadNotifications()->count(),
        );
    }

    public function forgetUnreadCount(User $user): void
    {
        $this->cache->forget($this->unreadCacheKey($user));
    }

    /**
     * @return TableBuilder<DatabaseNotification>
     */
    protected function table(User $user, Request $request): TableBuilder
    {
        /** @var Builder<DatabaseNotification> $query */
        $query = $user->notifications()->getQuery();

        return TableBuilder::for($query, $request, 'notifications')
            ->columns([
                Column::make('title')->searchable('data')->locked(),
                Column::make('level'),
                Column::make('created_at', __('Received'))->sortable(),
                Column::make('read_at', __('Read'))->sortable(),
            ])
            ->filters([
                Filter::make('status')
                    ->options(['Unread' => 'unread', 'Read' => 'read'])
                    ->using(static function (Builder $query, mixed $value): void {
                        if ($value === 'read') {
                            $query->whereNotNull('read_at');

                            return;
                        }

                        $query->whereNull('read_at');
                    }),

                // `data` is a JSON text column, and the level lives inside it.
                // The needle is constrained to the enum before it reaches SQL.
                Filter::make('level')
                    ->fromEnum(NotificationLevel::class)
                    ->using(static function (Builder $query, mixed $value): void {
                        $level = NotificationLevel::tryFrom(is_string($value) ? $value : '');

                        if ($level instanceof NotificationLevel) {
                            $query->where('data', 'like', '%"level":"'.$level->value.'"%');
                        }
                    }),

                Filter::make('received', __('Received'))->dateRange()->column('created_at'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function unreadCacheKey(User $user): string
    {
        return "notifications:{$user->id}:unread";
    }
}
