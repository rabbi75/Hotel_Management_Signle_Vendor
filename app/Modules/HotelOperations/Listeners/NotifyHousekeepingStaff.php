<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Listeners;

use App\Modules\HotelOperations\Events\HousekeepingTaskAssigned;
use App\Modules\HotelOperations\Events\HousekeepingTaskCreated;
use App\Modules\HotelOperations\Notifications\HousekeepingTaskAssignedNotification;
use App\Modules\HotelOperations\Notifications\HousekeepingTaskCreatedNotification;
use App\Modules\HotelOperations\Services\HotelStaffNotifier;

class NotifyHousekeepingStaff
{
    public function __construct(protected HotelStaffNotifier $notifier) {}

    public function handleCreated(HousekeepingTaskCreated $event): void
    {
        $this->notifier->notifyPermissionHolders(
            new HousekeepingTaskCreatedNotification($event->task),
            ['housekeeping.view', 'housekeeping.manage'],
            $event->task->company_id,
            auth()->user(),
        );
    }

    public function handleAssigned(HousekeepingTaskAssigned $event): void
    {
        $task = $event->task->loadMissing('assignee');

        if ($task->assignee !== null) {
            $this->notifier->notifyUser(
                $task->assignee,
                new HousekeepingTaskAssignedNotification($task),
            );
        }
    }
}
