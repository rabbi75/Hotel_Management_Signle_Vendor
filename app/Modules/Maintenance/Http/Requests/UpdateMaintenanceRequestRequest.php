<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Requests;

class UpdateMaintenanceRequestRequest extends StoreMaintenanceRequestRequest
{
    public function authorize(): bool
    {
        $request = $this->route('maintenanceRequest');

        return $request !== null && ($this->user()?->can('update', $request) ?? false);
    }
}
