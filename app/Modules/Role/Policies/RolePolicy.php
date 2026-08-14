<?php

declare(strict_types=1);

namespace App\Modules\Role\Policies;

use App\Modules\Role\Models\Role;
use App\Modules\User\Models\User;

/**
 * Authorisation for the RBAC configuration itself.
 *
 * Two structural rules sit above the permission checks:
 *  - the super-admin role is immutable, because it is the recovery path if the
 *    permission matrix is ever misconfigured;
 *  - a role still assigned to someone cannot be deleted, because the delete
 *    would silently strip capabilities from live accounts.
 *
 * A super admin bypasses every policy via the Gate::before in
 * AuthServiceProvider, so the actions re-assert the super-admin-role invariant
 * with an abort rather than relying on this class alone.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        if ($role->isSuperAdmin()) {
            return false;
        }

        return $user->can('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->isSuperAdmin() || $role->isSystem()) {
            return false;
        }

        if ($role->users()->exists()) {
            return false;
        }

        return $user->can('roles.delete');
    }

    /**
     * Editing the whole grid is the same capability as editing one role's
     * permissions.
     */
    public function updatePermissions(User $user): bool
    {
        return $user->can('roles.update');
    }

    public function assign(User $user): bool
    {
        return $user->can('roles.assign');
    }
}
