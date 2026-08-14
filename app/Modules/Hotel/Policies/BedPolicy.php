<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Policies;

use App\Modules\Hotel\Models\Bed;
use App\Modules\User\Models\User;

class BedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('beds.view');
    }

    public function view(User $user, Bed $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('beds.view');
    }

    public function create(User $user): bool
    {
        return $user->can('beds.manage');
    }

    public function update(User $user, Bed $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('beds.manage');
    }

    public function delete(User $user, Bed $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('beds.manage');
    }


    protected function inCurrentWorkspace(Bed $model): bool
    {
        return $model->company_id === current_company_id();
    }
}