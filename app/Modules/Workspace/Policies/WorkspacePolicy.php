<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Policies;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\CompanyMembership;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\WorkspaceMemberStatus;
use App\Modules\Workspace\Models\Workspace;
use App\Modules\Workspace\Models\WorkspaceMembership;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('operations.workspaces.view');
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->canAccess($user, $workspace) && $user->can('operations.workspaces.view');
    }

    public function create(User $user): bool
    {
        return $user->can('operations.workspaces.manage');
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->canAccess($user, $workspace) && $user->can('operations.workspaces.manage');
    }

  /**
   * Default workspaces are provisioned automatically and cannot be removed.
   */
    public function delete(User $user, Workspace $workspace): bool
    {
        return ! $workspace->is_default
            && $this->canAccess($user, $workspace)
            && $user->can('operations.workspaces.manage');
    }

    public function switchTo(User $user, Workspace $workspace): bool
    {
        return $this->canAccess($user, $workspace) && $workspace->status->value === 'active';
    }

    protected function canAccess(User $user, Workspace $workspace): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $membership = CompanyMembership::query()
            ->where('company_id', $workspace->company_id)
            ->where('user_id', $user->id)
            ->first();

        if ($membership !== null && in_array($membership->role, [CompanyRole::Owner, CompanyRole::Admin], true)) {
            return true;
        }

        return WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::Active->value)
            ->exists();
    }
}
