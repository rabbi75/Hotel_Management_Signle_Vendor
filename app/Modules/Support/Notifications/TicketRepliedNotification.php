<?php

declare(strict_types=1);

namespace App\Modules\Support\Notifications;

use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Notifications\BaseNotification;
use App\Modules\Platform\Models\Admin;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;

class TicketRepliedNotification extends BaseNotification
{
    public function __construct(
        protected SupportTicket $ticket,
        protected SupportTicketMessage $message,
        protected bool $fromAdmin,
    ) {}

    public function title(object $notifiable): string
    {
        return __('Update on ticket :number', ['number' => $this->ticket->number]);
    }

    public function body(object $notifiable): string
    {
        return $this->fromAdmin
            ? __('Support replied to “:subject”.', ['subject' => $this->ticket->subject])
            : __('The customer replied to “:subject”.', ['subject' => $this->ticket->subject]);
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Info;
    }

    public function actionUrl(object $notifiable): ?string
    {
        if ($notifiable instanceof Admin) {
            return route('admin.tickets.show', $this->ticket);
        }

        return route('support.show', $this->ticket);
    }

    public function actionLabel(): ?string
    {
        return __('View conversation');
    }
}
