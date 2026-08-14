<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Requests\Concerns;

use App\Modules\Role\Models\Role;

/**
 * Shared guard for requests that may carry role assignments.
 *
 * Handing out roles is a separate capability from editing a user, and the
 * super-admin role may only ever be granted by another super admin.
 */
trait ValidatesRoleAssignment
{
    protected function mayAssignRoles(): bool
    {
        if (! $this->has('roles')) {
            return true;
        }

        return $this->user()?->can('roles.assign') ?? false;
    }

    /**
     * @return list<string>
     */
    protected function assignableRoles(): array
    {
        $query = Role::query();

        if ($this->user()?->isSuperAdmin() !== true) {
            $query->assignable();
        }

        /** @var list<string> $names */
        $names = $query->pluck('name')->all();

        return $names;
    }
}
