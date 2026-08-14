<?php

declare(strict_types=1);

namespace App\Modules\Role\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Role\Actions\Concerns\FlushesPermissionCaches;
use App\Modules\Role\Models\Role;
use Illuminate\Support\Facades\DB;

class DeleteRole
{
    use FlushesPermissionCaches;

    public function __construct(protected SecurityLogger $security) {}

    public function handle(Role $role): void
    {
        abort_if($role->isSuperAdmin(), 403, __('The super admin role cannot be deleted.'));
        abort_if($role->isSystem(), 403, __('Built-in roles cannot be deleted.'));
        abort_if($role->users()->exists(), 409, __('Reassign the members of this role before deleting it.'));

        DB::transaction(function () use ($role): void {
            $context = ['role_id' => $role->id, 'role' => $role->name];

            $role->syncPermissions([]);
            $role->delete();

            $this->flushCachesForRole($role);

            $this->security->log(
                SecurityEvent::RoleDeleted,
                auth()->user(),
                __('Deleted role :role.', ['role' => $context['role']]),
                $context,
            );
        });
    }
}
