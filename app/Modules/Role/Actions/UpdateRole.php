<?php

declare(strict_types=1);

namespace App\Modules\Role\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Role\Actions\Concerns\FlushesPermissionCaches;
use App\Modules\Role\Models\Role;
use Illuminate\Support\Facades\DB;

class UpdateRole
{
    use FlushesPermissionCaches;

    public function __construct(protected SecurityLogger $security) {}

    /**
     * @param  list<string>|null  $permissions  Null leaves the permission set untouched.
     */
    public function handle(Role $role, string $name, ?array $permissions = null): Role
    {
        abort_if($role->isSuperAdmin(), 403, __('The super admin role cannot be modified.'));

        return DB::transaction(function () use ($role, $name, $permissions): Role {
            $previous = $role->name;

            // Renaming a role declared in config/permissions.php would break the
            // code that assigns it by name.
            if (! $role->isSystem()) {
                $role->update(['name' => $name]);
            }

            if ($permissions !== null) {
                $role->syncPermissions($permissions);
            }

            $this->flushCachesForRole($role);

            $this->security->log(
                SecurityEvent::RoleUpdated,
                auth()->user(),
                __('Updated role :role.', ['role' => $role->name]),
                ['role_id' => $role->id, 'previous_name' => $previous, 'permissions' => $permissions ?? []],
            );

            return $role->refresh();
        });
    }
}
