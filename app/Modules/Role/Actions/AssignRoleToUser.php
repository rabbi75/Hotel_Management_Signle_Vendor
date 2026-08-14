<?php

declare(strict_types=1);

namespace App\Modules\Role\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Role\Actions\Concerns\FlushesPermissionCaches;
use App\Modules\Role\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class AssignRoleToUser
{
    use FlushesPermissionCaches;

    public function __construct(protected SecurityLogger $security) {}

    /**
     * @param  list<string>  $roles  The complete set the user should end up with.
     */
    public function handle(User $user, array $roles): User
    {
        $superAdmin = (string) config('permissions.super_admin_role', 'super-admin');

        // Escalating someone to super admin is only ever a super admin's call,
        // whatever `roles.assign` might otherwise permit.
        abort_if(
            in_array($superAdmin, $roles, true) && auth()->user()?->isSuperAdmin() !== true,
            403,
            __('Only a super admin may grant the super admin role.'),
        );

        return DB::transaction(function () use ($user, $roles): User {
            $previous = $user->roles->pluck('name')->all();

            $user->syncRoles(Role::query()->whereIn('name', $roles)->get());

            $this->flushCachesForUser($user);

            $this->security->log(
                $roles === [] ? SecurityEvent::RoleRevoked : SecurityEvent::RoleAssigned,
                auth()->user(),
                __('Changed roles for :email.', ['email' => $user->email]),
                ['user_id' => $user->id, 'from' => $previous, 'to' => $roles],
            );

            return $user->refresh();
        });
    }
}
