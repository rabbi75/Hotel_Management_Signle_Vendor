<?php

declare(strict_types=1);

namespace App\Modules\Folio\Policies;

use App\Modules\Folio\Models\GuestFolio;
use App\Modules\User\Models\User;

class GuestFolioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('folios.view');
    }

    public function view(User $user, GuestFolio $folio): bool
    {
        return $this->inCurrentWorkspace($folio) && $user->can('folios.view');
    }

    public function manage(User $user, GuestFolio $folio): bool
    {
        return $this->inCurrentWorkspace($folio) && $user->can('folios.manage');
    }

    public function addCharge(User $user, GuestFolio $folio): bool
    {
        return $this->manage($user, $folio);
    }

    public function recordPayment(User $user, GuestFolio $folio): bool
    {
        return $this->inCurrentWorkspace($folio) && $user->can('folios.payments');
    }

    public function close(User $user, GuestFolio $folio): bool
    {
        return $this->manage($user, $folio);
    }

    protected function inCurrentWorkspace(GuestFolio $folio): bool
    {
        return $folio->company_id === current_company_id();
    }
}
