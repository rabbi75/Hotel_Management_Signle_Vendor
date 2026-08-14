<?php

declare(strict_types=1);

namespace App\Modules\Settings\Policies;

use App\Modules\Platform\Models\Admin;
use App\Modules\Settings\Models\Setting;

/**
 * Authorisation for the settings panels.
 *
 * Typed to {@see Admin}, not to a tenant `User`: every panel these guard writes
 * the *system* scope, so they configure the installation rather than a
 * workspace. The `auth:admin` middleware makes `admin` the request's default
 * guard, so `Gate::authorize('viewAny', Setting::class)` in the console resolves
 * an Admin here — and a tenant user, being the wrong type, is refused before a
 * check even runs.
 *
 * Checks are permission based — never role based — so an operator can re-cut
 * the console's roles without touching code.
 */
class SettingsPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('platform.settings.view');
    }

    public function view(Admin $admin, Setting $setting): bool
    {
        return $admin->can('platform.settings.view');
    }

    public function updateGeneral(Admin $admin): bool
    {
        return $admin->can('platform.settings.general');
    }

    public function updateMail(Admin $admin): bool
    {
        return $admin->can('platform.settings.mail');
    }

    public function updateStorage(Admin $admin): bool
    {
        return $admin->can('platform.settings.storage');
    }

    public function updateSecurity(Admin $admin): bool
    {
        return $admin->can('platform.settings.security');
    }

    public function manageApiKeys(Admin $admin): bool
    {
        return $admin->can('platform.settings.api_keys');
    }

    public function manageAi(Admin $admin): bool
    {
        return $admin->can('platform.settings.ai');
    }

    public function toggleMaintenance(Admin $admin): bool
    {
        return $admin->can('platform.settings.maintenance');
    }
}
