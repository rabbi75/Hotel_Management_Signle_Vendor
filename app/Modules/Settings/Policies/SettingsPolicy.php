<?php

declare(strict_types=1);

namespace App\Modules\Settings\Policies;

use App\Modules\Platform\Models\Admin;
use App\Modules\Settings\Models\Setting;
use App\Modules\User\Models\User;

/**
 * Authorisation for the settings panels.
 *
 * Every panel these guard writes the *system* scope, so they configure the
 * installation rather than a single property. Hotel staff on the web guard and
 * console operators on the admin guard are both accepted; permission checks
 * decide who may actually change anything.
 */
class SettingsPolicy
{
    public function viewAny(User|Admin $actor): bool
    {
        return $actor->can('platform.settings.view');
    }

    public function view(User|Admin $actor, Setting $setting): bool
    {
        return $actor->can('platform.settings.view');
    }

    public function updateGeneral(User|Admin $actor): bool
    {
        return $actor->can('platform.settings.general');
    }

    public function updateMail(User|Admin $actor): bool
    {
        return $actor->can('platform.settings.mail');
    }

    public function updateStorage(User|Admin $actor): bool
    {
        return $actor->can('platform.settings.storage');
    }

    public function updateSecurity(User|Admin $actor): bool
    {
        return $actor->can('platform.settings.security');
    }

    public function manageApiKeys(User|Admin $actor): bool
    {
        return $actor->can('platform.settings.api_keys');
    }

    public function manageAi(User|Admin $actor): bool
    {
        return $actor->can('platform.settings.ai');
    }

    public function toggleMaintenance(User|Admin $actor): bool
    {
        return $actor->can('platform.settings.maintenance');
    }
}
