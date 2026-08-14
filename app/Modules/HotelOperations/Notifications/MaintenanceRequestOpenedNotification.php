<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Notifications;

use App\Modules\Maintenance\Models\MaintenanceRequest;
use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Notifications\BaseNotification;
use Illuminate\Support\Facades\Route;

class MaintenanceRequestOpenedNotification extends BaseNotification
{
    public function __construct(protected MaintenanceRequest $request)
    {
        $this->request->loadMissing(['room', 'hotel']);
    }

    public function title(object $notifiable): string
    {
        return __('Maintenance work order opened');
    }

    public function body(object $notifiable): string
    {
        return __(':number — :title (:priority).', [
            'number' => $this->request->number,
            'title' => $this->request->title,
            'priority' => $this->request->priority->label(),
        ]);
    }

    public function level(): NotificationLevel
    {
        return match ($this->request->priority->value) {
            'urgent' => NotificationLevel::Critical,
            'high' => NotificationLevel::Warning,
            default => NotificationLevel::Info,
        };
    }

    public function icon(): string
    {
        return 'wrench';
    }

    public function actionUrl(object $notifiable): ?string
    {
        return Route::has('maintenance.show')
            ? route('maintenance.show', $this->request)
            : null;
    }

    public function actionLabel(): ?string
    {
        return __('View work order');
    }
}
