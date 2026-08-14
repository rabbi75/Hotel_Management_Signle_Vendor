<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Policies;

use App\Modules\Hotel\Models\Room;
use App\Modules\User\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('rooms.view');
    }

    public function view(User $user, Room $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('rooms.view');
    }

    public function create(User $user): bool
    {
        return $user->can('rooms.create');
    }

    public function update(User $user, Room $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('rooms.update');
    }

    public function delete(User $user, Room $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('rooms.delete');
    }


    protected function inCurrentWorkspace(Room $model): bool
    {
        return $model->company_id === current_company_id();
    }
}