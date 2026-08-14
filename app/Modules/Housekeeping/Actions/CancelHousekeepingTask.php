<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Actions;

use App\Modules\Housekeeping\Enums\HousekeepingTaskStatus;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelHousekeepingTask
{
    public function handle(HousekeepingTask $task): HousekeepingTask
    {
        if (! $task->status->canCancel()) {
            throw ValidationException::withMessages([
                'status' => __('This task cannot be cancelled.'),
            ]);
        }

        return DB::transaction(function () use ($task): HousekeepingTask {
            $task->status = HousekeepingTaskStatus::Cancelled;
            $task->save();

            return $task->fresh(['room', 'hotel']) ?? $task;
        });
    }
}
