<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Policies;

use App\Modules\Hotel\Models\RoomType;
use App\Modules\User\Models\User;

class RoomTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('room_types.view');
    }

    public function view(User $user, RoomType $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('room_types.view');
    }

    public function create(User $user): bool
    {
        return $user->can('room_types.manage');
    }

    public function update(User $user, RoomType $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('room_types.manage');
    }

    public function delete(User $user, RoomType $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('room_types.manage');
    }


    protected function inCurrentWorkspace(RoomType $model): bool
    {
        return $model->company_id === current_company_id();
    }
}