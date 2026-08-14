<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Services;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\WorkspaceMemberStatus;
use App\Modules\Workspace\Enums\WorkspaceStatus;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

class AccessibleWorkspaces
{
    /**
     * @return Collection<int, Workspace>
     */
    public function forUser(?User $user, ?Company $company = null): Collection
    {
        $company ??= current_company();

        if ($user === null || $company === null) {
            return new Collection;
        }

        if ($user->isSuperAdmin()) {
            return Workspace::query()
                ->where('company_id', $company->id)
                ->where('status', WorkspaceStatus::Active->value)
                ->orderBy('name')
                ->get();
        }

        $role = $user->membershipRole($company);

        if ($role !== null && in_array($role, [CompanyRole::Owner, CompanyRole::Admin], true)) {
            return Workspace::query()
                ->where('company_id', $company->id)
                ->where('status', WorkspaceStatus::Active->value)
                ->orderBy('name')
                ->get();
        }

        return $user->workspaces()
            ->where('workspaces.company_id', $company->id)
            ->where('workspace_user.status', WorkspaceMemberStatus::Active->value)
            ->where('workspaces.status', WorkspaceStatus::Active->value)
            ->orderBy('workspaces.name')
            ->get();
    }
}
