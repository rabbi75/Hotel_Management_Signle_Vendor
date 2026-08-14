<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Notifications;

use App\Modules\OnlineBooking\Services\PublicRoomCatalogService;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Reservation $reservation)
    {
        $this->reservation->loadMissing(['guest', 'hotel', 'roomType']);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = app(PublicRoomCatalogService::class)->confirmationUrl($this->reservation->hotel_id, $this->reservation->number);
        $hotel = $this->reservation->hotel?->name ?? __('the hotel');

        $message = (new MailMessage)
            ->subject(__('Your stay request at :hotel was cancelled', ['hotel' => $hotel]))
            ->greeting(__('Request cancelled'))
            ->line(__('Reservation :number at :hotel is no longer active.', [
                'number' => $this->reservation->number,
                'hotel' => $hotel,
            ]));

        if ($url !== null) {
            $message->action(__('View request'), $url);
        }

        return $message->line(__('If you still need a room, you can send a new request from the booking page.'));
    }
}
