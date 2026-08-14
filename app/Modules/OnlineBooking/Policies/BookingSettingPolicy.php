<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Policies;

use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\User\Models\User;

class BookingSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('online_booking.book')
            || $user->can('online_booking.manage')
            || $user->can('reservations.view');
    }

    public function view(User $user, BookingSetting $setting): bool
    {
        return $this->inCurrentWorkspace($setting) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('online_booking.book') || $user->can('reservations.create');
    }

    public function update(User $user, BookingSetting $setting): bool
    {
        return $this->inCurrentWorkspace($setting) && $user->can('online_booking.manage');
    }

    protected function inCurrentWorkspace(BookingSetting $setting): bool
    {
        return $setting->company_id === current_company_id();
    }
}
