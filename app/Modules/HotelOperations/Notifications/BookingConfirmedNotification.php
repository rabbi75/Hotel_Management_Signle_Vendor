<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Notifications;

use App\Modules\OnlineBooking\Services\PublicRoomCatalogService;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Reservation $reservation)
    {
        $this->reservation->loadMissing(['guest', 'hotel', 'roomType', 'room']);
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
            ->subject(__('Your stay at :hotel is confirmed', ['hotel' => $hotel]))
            ->greeting(__('You are confirmed'))
            ->line(__(':hotel has confirmed reservation :number.', [
                'hotel' => $hotel,
                'number' => $this->reservation->number,
            ]))
            ->line(__('Dates: :check_in → :check_out', [
                'check_in' => $this->reservation->check_in_date->toDateString(),
                'check_out' => $this->reservation->check_out_date->toDateString(),
            ]))
            ->line(__('Room: :room', [
                'room' => $this->reservation->room?->number
                    ?? $this->reservation->roomType?->name
                    ?? __('Assigned at check-in'),
            ]));

        if ($url !== null) {
            $message->action(__('View confirmation'), $url);
        }

        return $message->line(__('Please bring photo ID at check-in.'));
    }
}
