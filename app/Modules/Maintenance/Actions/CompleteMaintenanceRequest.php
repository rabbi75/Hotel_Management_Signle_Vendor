<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Hotel\Services\RoomStatusSync;
use App\Modules\Maintenance\Enums\MaintenanceRequestStatus;
use App\Modules\Maintenance\Models\MaintenanceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteMaintenanceRequest
{
    public function __construct(protected RoomStatusSync $roomStatus) {}

    /**
     * @param  array{resolution_notes?: string|null}  $options
     */
    public function handle(MaintenanceRequest $request, array $options = []): MaintenanceRequest
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('This work order is already closed.'),
            ]);
        }

        return DB::transaction(function () use ($request, $options): MaintenanceRequest {
            if ($request->status === MaintenanceRequestStatus::Open) {
                $request->started_at = CarbonImmutable::now();
            }

            $request->status = MaintenanceRequestStatus::Completed;
            $request->completed_at = CarbonImmutable::now();

            if (array_key_exists('resolution_notes', $options) && is_string($options['resolution_notes'])) {
                $request->resolution_notes = trim($options['resolution_notes']) ?: null;
            }

            $request->save();

            $request->loadMissing(['room', 'bed']);

            $this->roomStatus->releaseMaintenanceBlock($request->room);
            $this->roomStatus->releaseBedMaintenanceBlock($request->bed);

            return $request->fresh(['room', 'bed', 'hotel', 'assignee']) ?? $request;
        });
    }
}
