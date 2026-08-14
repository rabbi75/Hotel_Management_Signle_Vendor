<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Hotel\Services\RoomStatusSync;
use App\Modules\Maintenance\Enums\MaintenanceRequestStatus;
use App\Modules\Maintenance\Models\MaintenanceRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelMaintenanceRequest
{
    public function __construct(protected RoomStatusSync $roomStatus) {}

    public function handle(MaintenanceRequest $request): MaintenanceRequest
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('This work order is already closed.'),
            ]);
        }

        return DB::transaction(function () use ($request): MaintenanceRequest {
            $request->status = MaintenanceRequestStatus::Cancelled;
            $request->save();

            $request->loadMissing(['room', 'bed']);

            $this->roomStatus->releaseMaintenanceBlock($request->room);
            $this->roomStatus->releaseBedMaintenanceBlock($request->bed);

            return $request->fresh(['room', 'bed', 'hotel']) ?? $request;
        });
    }
}
