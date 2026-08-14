<?php

declare(strict_types=1);

namespace App\Modules\Support\Notifications;

use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Notifications\BaseNotification;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;

class TicketStatusChangedNotification extends BaseNotification
{
    public function __construct(
        protected SupportTicket $ticket,
        protected TicketStatus $status,
    ) {}

    public function title(object $notifiable): string
    {
        return __('Ticket :number is now :status', [
            'number' => $this->ticket->number,
            'status' => $this->status->label(),
        ]);
    }

    public function body(object $notifiable): string
    {
        return __('“:subject” was marked :status.', [
            'subject' => $this->ticket->subject,
            'status' => strtolower($this->status->label()),
        ]);
    }

    public function level(): NotificationLevel
    {
        return match ($this->status) {
            TicketStatus::Resolved => NotificationLevel::Success,
            TicketStatus::Closed => NotificationLevel::Info,
            default => NotificationLevel::Warning,
        };
    }

    public function actionUrl(object $notifiable): ?string
    {
        return route('support.show', $this->ticket);
    }

    public function actionLabel(): ?string
    {
        return __('View ticket');
    }
}
