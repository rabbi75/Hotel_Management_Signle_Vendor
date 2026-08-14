<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Events;

use App\Modules\Maintenance\Models\MaintenanceRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaintenanceRequestCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly MaintenanceRequest $request) {}
}
