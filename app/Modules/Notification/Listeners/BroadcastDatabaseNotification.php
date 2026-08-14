<?php

declare(strict_types=1);

namespace App\Modules\Notification\Listeners;

use App\Modules\Notification\Events\NotificationCreated;
use App\Modules\Notification\Http\Resources\NotificationResource;
use App\Modules\Notification\Services\NotificationCenter;
use App\Modules\User\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Bridges Laravel's database channel to the app's own realtime event.
 *
 * Hooking the sent event rather than each notification means every notification
 * — including ones added later by another module — busts the unread cache and
 * reaches open tabs without opting in.
 */
class BroadcastDatabaseNotification
{
    public function __construct(protected NotificationCenter $center) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
            return;
        }

        $user = $event->notifiable;
        $this->center->forgetUnreadCount($user);

        if (! $event->response instanceof DatabaseNotification) {
            return;
        }

        NotificationCreated::dispatch(
            $user->id,
            (new NotificationResource($event->response))->resolve(),
            $this->center->unreadCount($user),
        );
    }
}
