<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Listeners;

use App\Modules\HotelOperations\Events\MaintenanceRequestCreated;
use App\Modules\HotelOperations\Notifications\MaintenanceRequestOpenedNotification;
use App\Modules\HotelOperations\Services\HotelStaffNotifier;
use App\Modules\Maintenance\Enums\MaintenancePriority;

class NotifyMaintenanceStaff
{
    public function __construct(protected HotelStaffNotifier $notifier) {}

    public function handle(MaintenanceRequestCreated $event): void
    {
        $request = $event->request->loadMissing('assignee');

        if ($request->assigned_to !== null && $request->assignee !== null) {
            $this->notifier->notifyUser(
                $request->assignee,
                new MaintenanceRequestOpenedNotification($request),
            );

            return;
        }

        if (! in_array($request->priority, [MaintenancePriority::High, MaintenancePriority::Urgent], true)) {
            return;
        }

        $this->notifier->notifyPermissionHolders(
            new MaintenanceRequestOpenedNotification($request),
            ['maintenance.view', 'maintenance.assign'],
            $request->company_id,
            auth()->user(),
        );
    }
}
