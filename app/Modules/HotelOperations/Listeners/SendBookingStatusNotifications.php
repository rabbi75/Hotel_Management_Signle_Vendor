<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Listeners;

use App\Modules\HotelOperations\Events\ReservationCancelled;
use App\Modules\HotelOperations\Events\ReservationConfirmed;
use App\Modules\HotelOperations\Events\WebsiteBookingRequested;
use App\Modules\HotelOperations\Notifications\BookingCancelledNotification;
use App\Modules\HotelOperations\Notifications\BookingConfirmedNotification;
use App\Modules\HotelOperations\Notifications\BookingRequestReceivedNotification;
use App\Modules\HotelOperations\Notifications\WebsiteBookingReceivedNotification;
use App\Modules\HotelOperations\Services\HotelStaffNotifier;
use App\Modules\Reservation\Enums\BookingSource;
use Illuminate\Support\Facades\Notification;

class SendBookingStatusNotifications
{
    public function __construct(protected HotelStaffNotifier $notifier) {}

    public function handleRequested(WebsiteBookingRequested $event): void
    {
        $this->mailGuest($event->reservation->guest?->email, new BookingRequestReceivedNotification($event->reservation));

        $this->notifier->notifyPermissionHolders(
            new WebsiteBookingReceivedNotification($event->reservation),
            ['reservations.view', 'reservations.update'],
            $event->reservation->company_id,
        );
    }

    public function handleConfirmed(ReservationConfirmed $event): void
    {
        $this->mailGuest($event->reservation->guest?->email, new BookingConfirmedNotification($event->reservation));
    }

    public function handleCancelled(ReservationCancelled $event): void
    {
        if ($event->reservation->booking_source !== BookingSource::Website) {
            return;
        }

        $this->mailGuest($event->reservation->guest?->email, new BookingCancelledNotification($event->reservation));
    }

    protected function mailGuest(?string $email, BookingRequestReceivedNotification|BookingConfirmedNotification|BookingCancelledNotification $notification): void
    {
        if ($email === null || $email === '') {
            return;
        }

        Notification::route('mail', $email)->notify($notification);
    }
}
