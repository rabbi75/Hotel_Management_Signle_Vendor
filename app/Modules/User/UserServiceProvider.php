<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\User\Events\UserCreated;
use App\Modules\User\Events\UserDeleted;
use App\Modules\User\Events\UserRestored;
use App\Modules\User\Events\UserSuspended;
use App\Modules\User\Listeners\FlushUserCaches;
use App\Modules\User\Listeners\SendWelcomeNotification;
use App\Modules\User\Models\User;
use App\Modules\User\Policies\UserPolicy;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;
use Illuminate\Support\Facades\Event;

class UserServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        User::class => UserPolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->registerListeners();
        $this->registerNavigation();
    }

    protected function registerListeners(): void
    {
        Event::listen(UserCreated::class, SendWelcomeNotification::class);

        foreach ([UserSuspended::class, UserRestored::class, UserDeleted::class] as $event) {
            Event::listen($event, FlushUserCaches::class);
        }
    }

    protected function registerNavigation(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Administration', 60)->items([
                NavigationItem::make('Users', 'users.index')
                    ->icon('users')
                    ->permissions('users.view')
                    ->activeWhen('users.*')
                    ->order(5),
            ]),
        );
    }
}
