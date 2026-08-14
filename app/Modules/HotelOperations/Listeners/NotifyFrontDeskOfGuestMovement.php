<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Listeners;

use App\Modules\HotelOperations\Events\GuestCheckedIn;
use App\Modules\HotelOperations\Events\GuestCheckedOut;
use App\Modules\HotelOperations\Notifications\GuestCheckedInNotification;
use App\Modules\HotelOperations\Notifications\GuestCheckedOutNotification;
use App\Modules\HotelOperations\Services\HotelStaffNotifier;

class NotifyFrontDeskOfGuestMovement
{
    public function __construct(protected HotelStaffNotifier $notifier) {}

    public function handleCheckIn(GuestCheckedIn $event): void
    {
        $this->notifier->notifyPermissionHolders(
            new GuestCheckedInNotification($event->reservation),
            ['reservations.view', 'reservations.check_in'],
            $event->reservation->company_id,
            auth()->user(),
        );
    }

    public function handleCheckOut(GuestCheckedOut $event): void
    {
        $this->notifier->notifyPermissionHolders(
            new GuestCheckedOutNotification($event->reservation),
            ['reservations.view', 'reservations.check_out'],
            $event->reservation->company_id,
            auth()->user(),
        );
    }
}
