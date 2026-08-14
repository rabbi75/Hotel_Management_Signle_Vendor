<?php

declare(strict_types=1);

namespace App\Modules\HotelPos\Policies;

use App\Modules\HotelPos\Models\Restaurant;
use App\Modules\User\Models\User;

class RestaurantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hotel_pos.view');
    }

    public function view(User $user, Restaurant $restaurant): bool
    {
        return $this->inCurrentWorkspace($restaurant) && $user->can('hotel_pos.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hotel_pos.manage');
    }

    public function update(User $user, Restaurant $restaurant): bool
    {
        return $this->inCurrentWorkspace($restaurant) && $user->can('hotel_pos.manage');
    }

    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $this->inCurrentWorkspace($restaurant) && $user->can('hotel_pos.manage');
    }

    protected function inCurrentWorkspace(Restaurant $restaurant): bool
    {
        return $restaurant->company_id === current_company_id();
    }
}
