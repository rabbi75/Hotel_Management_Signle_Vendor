<?php

declare(strict_types=1);

namespace App\Modules\Company\Policies;

use App\Modules\Company\Models\Department;
use App\Modules\User\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('companies.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->inCurrentWorkspace($department) && $user->can('companies.view');
    }

    public function create(User $user): bool
    {
        return $user->can('companies.departments.manage');
    }

    public function update(User $user, Department $department): bool
    {
        return $this->inCurrentWorkspace($department) && $user->can('companies.departments.manage');
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->update($user, $department);
    }

    /**
     * The global scope already constrains queries, but a model resolved by an
     * explicit id (an import, a job) must still be re-checked.
     */
    protected function inCurrentWorkspace(Department $department): bool
    {
        return $department->company_id === current_company_id();
    }
}
