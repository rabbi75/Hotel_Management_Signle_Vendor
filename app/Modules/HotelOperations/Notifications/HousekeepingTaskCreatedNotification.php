<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Notifications;

use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Notifications\BaseNotification;
use Illuminate\Support\Facades\Route;

class HousekeepingTaskCreatedNotification extends BaseNotification
{
    public function __construct(protected HousekeepingTask $task)
    {
        $this->task->loadMissing(['room', 'hotel']);
    }

    public function title(object $notifiable): string
    {
        return __('New housekeeping task');
    }

    public function body(object $notifiable): string
    {
        return __(':task created for room :room (:type).', [
            'task' => $this->task->number,
            'room' => $this->task->room?->number ?? __('unknown'),
            'type' => $this->task->task_type->label(),
        ]);
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Warning;
    }

    public function icon(): string
    {
        return 'brush';
    }

    public function actionUrl(object $notifiable): ?string
    {
        return Route::has('housekeeping.show')
            ? route('housekeeping.show', $this->task)
            : null;
    }

    public function actionLabel(): ?string
    {
        return __('View task');
    }
}
