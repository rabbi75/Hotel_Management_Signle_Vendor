<?php

declare(strict_types=1);

namespace App\Modules\Company\Policies;

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('companies.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company) && $user->can('companies.view');
    }

    public function create(User $user): bool
    {
        return $user->ownedCompanies()->count() < (int) config('saas.workspace.max_owned_per_user');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company) && $user->can('companies.update');
    }

    /**
     * Deleting a workspace destroys every record inside it, so it is reserved
     * for the owner regardless of how permissive their role is.
     */
    public function delete(User $user, Company $company): bool
    {
        return $company->isOwnedBy($user);
    }

    public function transferOwnership(User $user, Company $company): bool
    {
        return $company->isOwnedBy($user);
    }

    public function viewMembers(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company) && $user->can('companies.members.view');
    }

    public function inviteMembers(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company) && $user->can('companies.members.invite');
    }

    public function updateMember(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company) && $user->can('companies.members.update');
    }

    public function removeMember(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company) && $user->can('companies.members.remove');
    }

    /**
     * Switching is a membership question, not a permission question: a guest
     * with almost no rights must still be able to enter their own workspace.
     */
    public function switchTo(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company) && $company->is_active;
    }
}
