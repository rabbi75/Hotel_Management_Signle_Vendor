<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Policies;

use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\User\Models\User;

class HousekeepingTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('housekeeping.view');
    }

    public function view(User $user, HousekeepingTask $task): bool
    {
        return $this->inCurrentWorkspace($task) && $user->can('housekeeping.view');
    }

    public function create(User $user): bool
    {
        return $user->can('housekeeping.manage');
    }

    public function update(User $user, HousekeepingTask $task): bool
    {
        return $this->inCurrentWorkspace($task) && $user->can('housekeeping.manage');
    }

    public function assign(User $user, HousekeepingTask $task): bool
    {
        return $this->inCurrentWorkspace($task) && $user->can('housekeeping.assign');
    }

    public function complete(User $user, HousekeepingTask $task): bool
    {
        return $this->inCurrentWorkspace($task) && $user->can('housekeeping.manage');
    }

    protected function inCurrentWorkspace(HousekeepingTask $task): bool
    {
        return $task->company_id === current_company_id();
    }
}
