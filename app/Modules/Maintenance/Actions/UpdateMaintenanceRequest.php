<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\DTOs\MaintenanceRequestData;
use App\Modules\Maintenance\Enums\MaintenanceCategory;
use App\Modules\Maintenance\Enums\MaintenancePriority;
use App\Modules\Maintenance\Models\MaintenanceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateMaintenanceRequest
{
    public function handle(MaintenanceRequest $request, MaintenanceRequestData $data): MaintenanceRequest
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('Closed work orders cannot be edited.'),
            ]);
        }

        return DB::transaction(function () use ($request, $data): MaintenanceRequest {
            $attributes = $data->toUpdateAttributes();

            if (isset($attributes['category'])) {
                $attributes['category'] = MaintenanceCategory::tryFrom($data->category) ?? MaintenanceCategory::Other;
            }

            if (isset($attributes['priority'])) {
                $attributes['priority'] = MaintenancePriority::tryFrom($data->priority) ?? MaintenancePriority::Normal;
            }

            if (array_key_exists('due_at', $attributes)) {
                $attributes['due_at'] = $data->dueAt !== null ? CarbonImmutable::parse($data->dueAt) : null;
            }

            $request->fill($attributes);
            $request->save();

            return $request->fresh(['room', 'bed', 'hotel', 'assignee']) ?? $request;
        });
    }
}
