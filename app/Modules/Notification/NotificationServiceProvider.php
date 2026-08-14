<?php

declare(strict_types=1);

namespace App\Modules\Notification;

use App\Modules\Notification\Console\PruneNotificationsCommand;
use App\Modules\Notification\Listeners\BroadcastDatabaseNotification;
use App\Modules\Notification\Services\NotificationCenter;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

class NotificationServiceProvider extends ModuleServiceProvider
{
    protected function registerModule(): void
    {
        // Shared by the Inertia middleware on every request, so a single
        // instance per request keeps the unread count to one cache read.
        $this->app->singleton(NotificationCenter::class);
    }

    protected function bootModule(): void
    {
        Event::listen(NotificationSent::class, BroadcastDatabaseNotification::class);

        if ($this->app->runningInConsole()) {
            $this->commands([PruneNotificationsCommand::class]);
        }
    }
}
