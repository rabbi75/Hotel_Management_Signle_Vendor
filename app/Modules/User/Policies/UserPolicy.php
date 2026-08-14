<?php

declare(strict_types=1);

namespace App\Modules\User\Policies;

use App\Modules\User\Models\User;

/**
 * Authorisation for administering other people's accounts.
 *
 * Two invariants sit above the permission checks:
 *  - a user is always allowed to see and edit themselves, whatever their role;
 *  - a user may never suspend, delete or impersonate themselves, because those
 *    are the actions that would lock an administrator out of their own tenant.
 *
 * Super admins are handled by the Gate::before in AuthServiceProvider, so the
 * checks here only need to protect super admins from everyone else.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return true;
        }

        return $user->can('users.view') && $this->sharesWorkspace($model);
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return true;
        }

        return $user->can('users.update')
            && $this->sharesWorkspace($model)
            && ! $model->isSuperAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return ! $user->is($model)
            && $user->can('users.delete')
            && $this->sharesWorkspace($model)
            && ! $model->isSuperAdmin();
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can('users.suspend') && $this->sharesWorkspace($model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return ! $user->is($model)
            && $user->can('users.delete')
            && $this->sharesWorkspace($model)
            && ! $model->isSuperAdmin();
    }

    public function suspend(User $user, User $model): bool
    {
        return ! $user->is($model)
            && $user->can('users.suspend')
            && $this->sharesWorkspace($model)
            && ! $model->isSuperAdmin();
    }

    public function impersonate(User $user, User $model): bool
    {
        return ! $user->is($model)
            && $user->can('users.impersonate')
            && $this->sharesWorkspace($model)
            && ! $model->isSuperAdmin();
    }

    public function export(User $user): bool
    {
        return $user->can('users.export');
    }

    /**
     * Users are global records made visible through the company_user pivot, so
     * membership of the active workspace — not a company_id column — is what
     * decides whether one account may act on another.
     */
    protected function sharesWorkspace(User $model): bool
    {
        $companyId = current_company_id();

        if ($companyId === null) {
            return false;
        }

        return $model->belongsToCompany($companyId);
    }
}
