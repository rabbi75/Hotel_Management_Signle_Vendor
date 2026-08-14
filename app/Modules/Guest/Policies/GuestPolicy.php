<?php

declare(strict_types=1);

namespace App\Modules\Guest\Policies;

use App\Modules\Guest\Models\Guest;
use App\Modules\User\Models\User;

class GuestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('guests.view');
    }

    public function view(User $user, Guest $guest): bool
    {
        return $this->inCurrentWorkspace($guest) && $user->can('guests.view');
    }

    public function create(User $user): bool
    {
        return $user->can('guests.create');
    }

    public function update(User $user, Guest $guest): bool
    {
        return $this->inCurrentWorkspace($guest) && $user->can('guests.update');
    }

    public function delete(User $user, Guest $guest): bool
    {
        return $this->inCurrentWorkspace($guest) && $user->can('guests.delete');
    }

    protected function inCurrentWorkspace(Guest $guest): bool
    {
        return $guest->company_id === current_company_id();
    }
}
