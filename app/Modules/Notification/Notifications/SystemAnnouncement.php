<?php

declare(strict_types=1);

namespace App\Modules\Notification\Notifications;

use App\Modules\Notification\Enums\NotificationLevel;

/**
 * The worked example of a {@see BaseNotification}: an ad-hoc message an
 * administrator broadcasts to members of a workspace.
 */
class SystemAnnouncement extends BaseNotification
{
    public function __construct(
        protected string $heading,
        protected string $message,
        protected NotificationLevel $level = NotificationLevel::Info,
        protected ?string $url = null,
        protected ?string $label = null,
    ) {}

    public function title(object $notifiable): string
    {
        return $this->heading;
    }

    public function body(object $notifiable): string
    {
        return $this->message;
    }

    public function level(): NotificationLevel
    {
        return $this->level;
    }

    public function actionUrl(object $notifiable): ?string
    {
        return $this->url;
    }

    public function actionLabel(): ?string
    {
        return $this->label;
    }
}
