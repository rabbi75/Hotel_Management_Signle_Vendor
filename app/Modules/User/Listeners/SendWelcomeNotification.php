<?php

declare(strict_types=1);

namespace App\Modules\User\Listeners;

use App\Modules\User\Events\UserCreated;
use App\Modules\User\Notifications\WelcomeNotification;

class SendWelcomeNotification
{
    public function handle(UserCreated $event): void
    {
        $event->user->notify(new WelcomeNotification($event->company));
    }
}
