<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Notifications;

use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Notifications\BaseNotification;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Support\Facades\Route;

class WebsiteBookingReceivedNotification extends BaseNotification
{
    public function __construct(protected Reservation $reservation)
    {
        $this->reservation->loadMissing(['guest', 'hotel', 'roomType']);
    }

    public function title(object $notifiable): string
    {
        return __('New website booking request');
    }

    public function body(object $notifiable): string
    {
        return __(':guest requested :type (:reservation). Confirm it from reservations.', [
            'guest' => $this->reservation->guest?->fullName() ?? __('A guest'),
            'type' => $this->reservation->roomType?->name ?? __('a room'),
            'reservation' => $this->reservation->number,
        ]);
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Warning;
    }

    public function icon(): string
    {
        return 'calendar-days';
    }

    public function actionUrl(object $notifiable): ?string
    {
        return Route::has('reservations.show')
            ? route('reservations.show', $this->reservation)
            : null;
    }

    public function actionLabel(): ?string
    {
        return __('Review request');
    }
}
