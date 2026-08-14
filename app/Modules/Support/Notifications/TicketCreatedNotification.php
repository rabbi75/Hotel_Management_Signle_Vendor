<?php

declare(strict_types=1);

namespace App\Modules\Support\Notifications;

use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Notifications\BaseNotification;
use App\Modules\Support\Models\SupportTicket;

class TicketCreatedNotification extends BaseNotification
{
    public function __construct(protected SupportTicket $ticket) {}

    public function title(object $notifiable): string
    {
        return __('New support ticket :number', ['number' => $this->ticket->number]);
    }

    public function body(object $notifiable): string
    {
        return __(':company opened “:subject”.', [
            'company' => $this->ticket->company?->name ?? __('A workspace'),
            'subject' => $this->ticket->subject,
        ]);
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Warning;
    }

    public function actionUrl(object $notifiable): ?string
    {
        return route('admin.tickets.show', $this->ticket);
    }

    public function actionLabel(): ?string
    {
        return __('Open ticket');
    }
}
