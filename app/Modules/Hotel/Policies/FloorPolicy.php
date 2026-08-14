<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Policies;

use App\Modules\Hotel\Models\Floor;
use App\Modules\User\Models\User;

class FloorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('floors.view');
    }

    public function view(User $user, Floor $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('floors.view');
    }

    public function create(User $user): bool
    {
        return $user->can('floors.manage');
    }

    public function update(User $user, Floor $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('floors.manage');
    }

    public function delete(User $user, Floor $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('floors.manage');
    }


    protected function inCurrentWorkspace(Floor $model): bool
    {
        return $model->company_id === current_company_id();
    }
}