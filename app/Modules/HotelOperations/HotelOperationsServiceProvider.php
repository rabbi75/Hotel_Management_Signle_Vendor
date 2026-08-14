<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations;

use App\Modules\HotelOperations\Events\GuestCheckedIn;
use App\Modules\HotelOperations\Events\GuestCheckedOut;
use App\Modules\HotelOperations\Events\HousekeepingTaskAssigned;
use App\Modules\HotelOperations\Events\HousekeepingTaskCreated;
use App\Modules\HotelOperations\Events\MaintenanceRequestCreated;
use App\Modules\HotelOperations\Events\ReservationCancelled;
use App\Modules\HotelOperations\Events\ReservationConfirmed;
use App\Modules\HotelOperations\Events\WebsiteBookingRequested;
use App\Modules\HotelOperations\Listeners\NotifyFrontDeskOfGuestMovement;
use App\Modules\HotelOperations\Listeners\NotifyHousekeepingStaff;
use App\Modules\HotelOperations\Listeners\NotifyMaintenanceStaff;
use App\Modules\HotelOperations\Listeners\SendBookingStatusNotifications;
use App\Modules\HotelOperations\Services\HotelStaffNotifier;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class HotelOperationsServiceProvider extends ModuleServiceProvider
{
    protected function registerModule(): void
    {
        $this->app->singleton(HotelStaffNotifier::class);
    }

    protected function bootModule(): void
    {
        $frontDesk = NotifyFrontDeskOfGuestMovement::class;
        $housekeeping = NotifyHousekeepingStaff::class;
        $maintenance = NotifyMaintenanceStaff::class;

        Event::listen(GuestCheckedIn::class, [$frontDesk, 'handleCheckIn']);
        Event::listen(GuestCheckedOut::class, [$frontDesk, 'handleCheckOut']);
        Event::listen(HousekeepingTaskCreated::class, [$housekeeping, 'handleCreated']);
        Event::listen(HousekeepingTaskAssigned::class, [$housekeeping, 'handleAssigned']);
        Event::listen(MaintenanceRequestCreated::class, [$maintenance, 'handle']);

        $bookingMail = SendBookingStatusNotifications::class;
        Event::listen(WebsiteBookingRequested::class, [$bookingMail, 'handleRequested']);
        Event::listen(ReservationConfirmed::class, [$bookingMail, 'handleConfirmed']);
        Event::listen(ReservationCancelled::class, [$bookingMail, 'handleCancelled']);
    }
}
