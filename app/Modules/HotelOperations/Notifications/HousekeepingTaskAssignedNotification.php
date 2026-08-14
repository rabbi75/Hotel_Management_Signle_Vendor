<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Notifications;

use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Notifications\BaseNotification;
use Illuminate\Support\Facades\Route;

class HousekeepingTaskAssignedNotification extends BaseNotification
{
    public function __construct(protected HousekeepingTask $task)
    {
        $this->task->loadMissing(['room', 'hotel']);
    }

    public function title(object $notifiable): string
    {
        return __('Housekeeping task assigned');
    }

    public function body(object $notifiable): string
    {
        return __('You were assigned :task for room :room.', [
            'task' => $this->task->number,
            'room' => $this->task->room?->number ?? __('unknown'),
        ]);
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Info;
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
        return __('Open task');
    }
}
