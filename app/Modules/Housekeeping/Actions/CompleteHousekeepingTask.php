<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Actions;

use App\Modules\Housekeeping\Enums\HousekeepingTaskStatus;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Hotel\Services\RoomStatusSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteHousekeepingTask
{
    public function __construct(protected RoomStatusSync $roomStatus) {}

    /**
     * @param  array{notes?: string|null}  $options
     */
    public function handle(HousekeepingTask $task, array $options = []): HousekeepingTask
    {
        if (! $task->status->canComplete() && $task->status !== HousekeepingTaskStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => __('This task cannot be completed.'),
            ]);
        }

        return DB::transaction(function () use ($task, $options): HousekeepingTask {
            if ($task->status === HousekeepingTaskStatus::Pending) {
                $task->started_at = CarbonImmutable::now();
            }

            $task->status = HousekeepingTaskStatus::Completed;
            $task->completed_at = CarbonImmutable::now();

            if (array_key_exists('notes', $options) && is_string($options['notes'])) {
                $task->notes = trim(($task->notes ? $task->notes."\n" : '').$options['notes']) ?: null;
            }

            $task->save();

            $task->loadMissing('room');

            if ($task->room !== null) {
                $this->roomStatus->markAvailableAfterHousekeeping($task->room);
            }

            return $task->fresh(['room', 'hotel', 'assignee']) ?? $task;
        });
    }
}
