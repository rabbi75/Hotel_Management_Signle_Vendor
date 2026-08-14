<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Policies;

use App\Modules\Hotel\Models\Building;
use App\Modules\User\Models\User;

class BuildingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('buildings.view');
    }

    public function view(User $user, Building $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('buildings.view');
    }

    public function create(User $user): bool
    {
        return $user->can('buildings.manage');
    }

    public function update(User $user, Building $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('buildings.manage');
    }

    public function delete(User $user, Building $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('buildings.manage');
    }


    protected function inCurrentWorkspace(Building $model): bool
    {
        return $model->company_id === current_company_id();
    }
}