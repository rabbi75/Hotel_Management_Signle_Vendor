<?php

declare(strict_types=1);

namespace App\Modules\User\Listeners;

use App\Modules\User\Events\UserDeleted;
use App\Modules\User\Events\UserRestored;
use App\Modules\User\Events\UserSuspended;
use App\Support\Navigation\NavigationBuilder;

/**
 * Permissions and navigation are cached per user; a lifecycle change has to
 * invalidate both or a suspended account keeps its sidebar until the TTL lapses.
 */
class FlushUserCaches
{
    public function __construct(protected NavigationBuilder $navigation) {}

    public function handle(UserSuspended|UserRestored|UserDeleted $event): void
    {
        $event->user->flushPermissionCache();
        $this->navigation->flushFor($event->user);
    }
}
