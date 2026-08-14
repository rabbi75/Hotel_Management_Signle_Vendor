<?php

declare(strict_types=1);

namespace App\Modules\Company\Policies;

use App\Modules\Company\Models\Team;
use App\Modules\User\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('companies.view');
    }

    public function view(User $user, Team $team): bool
    {
        return $this->inCurrentWorkspace($team) && $user->can('companies.view');
    }

    public function create(User $user): bool
    {
        return $user->can('companies.teams.manage');
    }

    public function update(User $user, Team $team): bool
    {
        return $this->inCurrentWorkspace($team) && $user->can('companies.teams.manage');
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }

    protected function inCurrentWorkspace(Team $team): bool
    {
        return $team->company_id === current_company_id();
    }
}
