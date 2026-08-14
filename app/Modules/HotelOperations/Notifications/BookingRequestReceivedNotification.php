<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Notifications;

use App\Modules\OnlineBooking\Services\PublicRoomCatalogService;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRequestReceivedNotification extends Notification implements ShouldQueue
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
            ->subject(__('We received your stay request at :hotel', ['hotel' => $hotel]))
            ->greeting(__('Request received'))
            ->line(__('Thank you. :hotel has your request :number. The front desk still needs to confirm it — this is not a guaranteed room yet.', [
                'hotel' => $hotel,
                'number' => $this->reservation->number,
            ]))
            ->line(__('Dates: :check_in → :check_out', [
                'check_in' => $this->reservation->check_in_date->toDateString(),
                'check_out' => $this->reservation->check_out_date->toDateString(),
            ]))
            ->line(__('Room type: :type', [
                'type' => $this->reservation->roomType?->name ?? __('Requested room'),
            ]));

        if ($url !== null) {
            $message->action(__('View your request'), $url);
        }

        return $message->line(__('We will email you again when the hotel confirms or cannot accommodate the stay.'));
    }
}
