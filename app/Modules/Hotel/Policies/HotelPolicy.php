<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Policies;

use App\Modules\Hotel\Models\Hotel;
use App\Modules\User\Models\User;

class HotelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hotels.view');
    }

    public function view(User $user, Hotel $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('hotels.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hotels.create');
    }

    public function update(User $user, Hotel $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('hotels.update');
    }

    public function delete(User $user, Hotel $model): bool
    {
        return $this->inCurrentWorkspace($model) && $user->can('hotels.delete');
    }

    public function switch(User $user, Hotel $hotel): bool
    {
        return $this->inCurrentWorkspace($hotel) && $user->can('hotels.switch');
    }

    protected function inCurrentWorkspace(Hotel $model): bool
    {
        return $model->company_id === current_company_id();
    }
}