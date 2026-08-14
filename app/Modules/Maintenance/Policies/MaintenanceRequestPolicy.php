<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Policies;

use App\Modules\Maintenance\Models\MaintenanceRequest;
use App\Modules\User\Models\User;

class MaintenanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.view');
    }

    public function view(User $user, MaintenanceRequest $request): bool
    {
        return $this->inCurrentWorkspace($request) && $user->can('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance.create');
    }

    public function update(User $user, MaintenanceRequest $request): bool
    {
        return $this->inCurrentWorkspace($request) && $user->can('maintenance.update');
    }

    public function assign(User $user, MaintenanceRequest $request): bool
    {
        return $this->inCurrentWorkspace($request) && $user->can('maintenance.assign');
    }

    public function complete(User $user, MaintenanceRequest $request): bool
    {
        return $this->inCurrentWorkspace($request) && $user->can('maintenance.complete');
    }

    protected function inCurrentWorkspace(MaintenanceRequest $request): bool
    {
        return $request->company_id === current_company_id();
    }
}
