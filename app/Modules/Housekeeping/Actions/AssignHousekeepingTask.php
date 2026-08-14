<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Actions;

use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\HotelOperations\Events\HousekeepingTaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignHousekeepingTask
{
    public function handle(HousekeepingTask $task, ?int $userId): HousekeepingTask
    {
        if (! $task->isOpen()) {
            throw ValidationException::withMessages([
                'assigned_to' => __('Closed tasks cannot be reassigned.'),
            ]);
        }

        $task = DB::transaction(function () use ($task, $userId): HousekeepingTask {
            $task->assigned_to = $userId;
            $task->save();

            return $task->fresh(['assignee', 'room', 'hotel']) ?? $task;
        });

        if ($userId !== null) {
            HousekeepingTaskAssigned::dispatch($task);
        }

        return $task;
    }
}
