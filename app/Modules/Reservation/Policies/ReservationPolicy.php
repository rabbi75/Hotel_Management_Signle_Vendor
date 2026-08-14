<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Policies;

use App\Modules\Reservation\Models\Reservation;
use App\Modules\User\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reservations.view');
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $this->inCurrentWorkspace($reservation) && $user->can('reservations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('reservations.create');
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $this->inCurrentWorkspace($reservation) && $user->can('reservations.update');
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $this->inCurrentWorkspace($reservation) && $user->can('reservations.cancel');
    }

    public function checkIn(User $user, Reservation $reservation): bool
    {
        return $this->inCurrentWorkspace($reservation) && $user->can('reservations.check_in');
    }

    public function checkOut(User $user, Reservation $reservation): bool
    {
        return $this->inCurrentWorkspace($reservation) && $user->can('reservations.check_out');
    }

    public function calendar(User $user): bool
    {
        return $user->can('reservations.calendar');
    }

    protected function inCurrentWorkspace(Reservation $reservation): bool
    {
        return $reservation->company_id === current_company_id();
    }
}
