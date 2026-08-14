<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Actions;

use App\Modules\Housekeeping\Enums\HousekeepingTaskStatus;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Hotel\Services\RoomStatusSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartHousekeepingTask
{
    public function __construct(protected RoomStatusSync $roomStatus) {}

    public function handle(HousekeepingTask $task): HousekeepingTask
    {
        if (! $task->status->canStart()) {
            throw ValidationException::withMessages([
                'status' => __('This task cannot be started.'),
            ]);
        }

        return DB::transaction(function () use ($task): HousekeepingTask {
            $task->status = HousekeepingTaskStatus::InProgress;
            $task->started_at = CarbonImmutable::now();
            $task->save();

            $task->loadMissing('room');

            if ($task->room !== null) {
                $this->roomStatus->markCleaning($task->room);
            }

            return $task->fresh(['room', 'hotel', 'assignee']) ?? $task;
        });
    }
}
