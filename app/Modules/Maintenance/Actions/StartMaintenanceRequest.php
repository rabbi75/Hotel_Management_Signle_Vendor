<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Enums\MaintenanceRequestStatus;
use App\Modules\Maintenance\Models\MaintenanceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartMaintenanceRequest
{
    public function handle(MaintenanceRequest $request): MaintenanceRequest
    {
        if ($request->status !== MaintenanceRequestStatus::Open && $request->status !== MaintenanceRequestStatus::OnHold) {
            throw ValidationException::withMessages([
                'status' => __('This work order cannot be started.'),
            ]);
        }

        return DB::transaction(function () use ($request): MaintenanceRequest {
            $request->status = MaintenanceRequestStatus::InProgress;
            $request->started_at ??= CarbonImmutable::now();
            $request->save();

            return $request->fresh(['room', 'hotel', 'assignee']) ?? $request;
        });
    }
}
