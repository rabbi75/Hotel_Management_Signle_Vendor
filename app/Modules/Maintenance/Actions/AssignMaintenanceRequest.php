<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\MaintenanceRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignMaintenanceRequest
{
    public function handle(MaintenanceRequest $request, ?int $userId): MaintenanceRequest
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'assigned_to' => __('Closed work orders cannot be reassigned.'),
            ]);
        }

        return DB::transaction(function () use ($request, $userId): MaintenanceRequest {
            $request->assigned_to = $userId;
            $request->save();

            return $request->fresh(['assignee', 'room', 'hotel']) ?? $request;
        });
    }
}
