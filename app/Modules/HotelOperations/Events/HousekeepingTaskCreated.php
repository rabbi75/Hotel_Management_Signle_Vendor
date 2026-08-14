<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Events;

use App\Modules\Housekeeping\Models\HousekeepingTask;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HousekeepingTaskCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly HousekeepingTask $task) {}
}
