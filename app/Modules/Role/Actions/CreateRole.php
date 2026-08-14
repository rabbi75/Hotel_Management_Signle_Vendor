<?php

declare(strict_types=1);

namespace App\Modules\Role\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Role\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class CreateRole
{
    public function __construct(
        protected SecurityLogger $security,
        protected PermissionRegistrar $registrar,
    ) {}

    /**
     * @param  list<string>  $permissions
     */
    public function handle(string $name, array $permissions = [], ?string $guard = null): Role
    {
        return DB::transaction(function () use ($name, $permissions, $guard): Role {
            $role = new Role;
            $role->fill([
                'name' => $name,
                'guard_name' => $guard ?? config('auth.defaults.guard', 'web'),
            ]);
            $role->save();

            $role->syncPermissions($permissions);

            $this->registrar->forgetCachedPermissions();

            $this->security->log(
                SecurityEvent::RoleCreated,
                auth()->user(),
                __('Created role :role.', ['role' => $role->name]),
                ['role_id' => $role->id, 'permissions' => $permissions],
            );

            return $role;
        });
    }
}
