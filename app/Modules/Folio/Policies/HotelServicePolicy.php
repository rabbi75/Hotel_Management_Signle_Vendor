<?php

declare(strict_types=1);

namespace App\Modules\Folio\Policies;

use App\Modules\Folio\Models\HotelService;
use App\Modules\User\Models\User;

class HotelServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hotel_services.view');
    }

    public function view(User $user, HotelService $service): bool
    {
        return $this->inCurrentWorkspace($service) && $user->can('hotel_services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hotel_services.manage');
    }

    public function update(User $user, HotelService $service): bool
    {
        return $this->inCurrentWorkspace($service) && $user->can('hotel_services.manage');
    }

    public function delete(User $user, HotelService $service): bool
    {
        return $this->inCurrentWorkspace($service) && $user->can('hotel_services.manage');
    }

    protected function inCurrentWorkspace(HotelService $service): bool
    {
        return $service->company_id === current_company_id();
    }
}
