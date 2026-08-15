<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Policies;

use App\Modules\OnlineBooking\Models\BookingPaymentMethod;
use App\Modules\User\Models\User;

class BookingPaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('online_booking.manage');
    }

    public function update(User $user, BookingPaymentMethod $method): bool
    {
        return $this->inCurrentWorkspace($method) && $user->can('online_booking.manage');
    }

    protected function inCurrentWorkspace(BookingPaymentMethod $method): bool
    {
        return $method->company_id === current_company_id();
    }
}
