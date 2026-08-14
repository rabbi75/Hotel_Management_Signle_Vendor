<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Policies;

use App\Modules\Hotel\Models\Facility;
use App\Modules\User\Models\User;

class FacilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('facilities.view');
    }

    public function view(User $user, Facility $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('facilities.view');
    }

    public function create(User $user): bool
    {
        return $user->can('facilities.manage');
    }

    public function update(User $user, Facility $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('facilities.manage');
    }

    public function delete(User $user, Facility $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('facilities.manage');
    }


    protected function inCurrentWorkspace(Facility $model): bool
    {
        return $model->company_id === current_company_id();
    }
}