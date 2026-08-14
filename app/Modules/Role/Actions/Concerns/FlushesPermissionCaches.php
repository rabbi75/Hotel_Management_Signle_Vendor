<?php

declare(strict_types=1);

namespace App\Modules\Role\Actions\Concerns;

use App\Modules\Role\Models\Role;
use App\Modules\User\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions are cached twice: once by the package registrar (the whole
 * role/permission graph) and once per user by {@see User::cachedPermissionNames()},
 * which the frontend reads. A grant that only clears one of them leaves the UI
 * asserting capabilities the backend has already revoked.
 */
trait FlushesPermissionCaches
{
    protected function flushCachesForRole(Role $role): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->users()->each(static function (mixed $user): void {
            if ($user instanceof User) {
                $user->flushPermissionCache();
            }
        });
    }

    protected function flushCachesForUser(User $user): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->flushPermissionCache();
    }
}
