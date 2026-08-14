<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Notifications;

use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Notifications\BaseNotification;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Support\Facades\Route;

class GuestCheckedInNotification extends BaseNotification
{
    public function __construct(protected Reservation $reservation)
    {
        $this->reservation->loadMissing(['guest', 'room', 'hotel']);
    }

    public function title(object $notifiable): string
    {
        return __('Guest checked in');
    }

    public function body(object $notifiable): string
    {
        return __(':guest checked in to :room (:reservation).', [
            'guest' => $this->reservation->guest?->fullName() ?? __('Guest'),
            'room' => $this->reservation->room?->number ?? __('unassigned room'),
            'reservation' => $this->reservation->number,
        ]);
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Success;
    }

    public function icon(): string
    {
        return 'log-in';
    }

    public function actionUrl(object $notifiable): ?string
    {
        return Route::has('reservations.show')
            ? route('reservations.show', $this->reservation)
            : null;
    }

    public function actionLabel(): ?string
    {
        return __('View reservation');
    }
}
