<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Actions;

use App\Modules\Guest\Models\Guest;
use App\Modules\OnlineBooking\Models\Customer;
use App\Modules\Reservation\Models\Reservation;

class LinkCustomerReservations
{
    public function handle(Customer $customer): void
    {
        $guestIds = Guest::query()
            ->withoutCompanyScope()
            ->withoutWorkspaceScope()
            ->where('email', $customer->email)
            ->pluck('id');

        if ($guestIds->isEmpty()) {
            return;
        }

        Reservation::query()
            ->withoutCompanyScope()
            ->withoutWorkspaceScope()
            ->whereIn('guest_id', $guestIds)
            ->whereNull('customer_id')
            ->update(['customer_id' => $customer->id]);
    }
}
