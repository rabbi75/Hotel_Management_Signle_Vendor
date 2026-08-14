<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Actions;

use App\Modules\Housekeeping\DTOs\HousekeepingTaskData;
use App\Modules\Housekeeping\Enums\HousekeepingPriority;
use App\Modules\Housekeeping\Enums\HousekeepingTaskStatus;
use App\Modules\Housekeeping\Enums\HousekeepingTaskType;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\HotelOperations\Events\HousekeepingTaskCreated;
use App\Modules\HotelOperations\Events\HousekeepingTaskAssigned;
use App\Modules\Hotel\Models\Room;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateHousekeepingTask
{
    public function handle(HousekeepingTaskData $data): HousekeepingTask
    {
        $task = DB::transaction(function () use ($data): HousekeepingTask {
            $room = Room::query()->where('hotel_id', $data->hotelId)->whereKey($data->roomId)->first();

            if (! $room instanceof Room) {
                throw ValidationException::withMessages([
                    'room_id' => __('The selected room does not belong to this property.'),
                ]);
            }

            if ($data->reservationId !== null) {
                $exists = HousekeepingTask::query()
                    ->where('reservation_id', $data->reservationId)
                    ->whereIn('status', [
                        HousekeepingTaskStatus::Pending->value,
                        HousekeepingTaskStatus::InProgress->value,
                    ])
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'reservation_id' => __('An open housekeeping task already exists for this reservation.'),
                    ]);
                }
            }

            $task = new HousekeepingTask([
                ...$data->toAttributes(),
                'number' => static::nextNumber(),
                'status' => HousekeepingTaskStatus::Pending,
                'priority' => HousekeepingPriority::tryFrom($data->priority) ?? HousekeepingPriority::Normal,
                'task_type' => HousekeepingTaskType::tryFrom($data->taskType) ?? HousekeepingTaskType::Other,
                'created_by' => auth()->id(),
            ]);
            $task->save();

            return $task->fresh(['room', 'hotel', 'reservation', 'assignee']) ?? $task;
        });

        HousekeepingTaskCreated::dispatch($task);

        if ($task->assigned_to !== null) {
            HousekeepingTaskAssigned::dispatch($task);
        }

        return $task;
    }

    public function fromCheckout(Reservation $reservation): ?HousekeepingTask
    {
        if ($reservation->room_id === null) {
            return null;
        }

        $existing = HousekeepingTask::query()
            ->where('reservation_id', $reservation->id)
            ->whereIn('status', [
                HousekeepingTaskStatus::Pending->value,
                HousekeepingTaskStatus::InProgress->value,
            ])
            ->first();

        if ($existing instanceof HousekeepingTask) {
            return $existing;
        }

        return $this->handle(new HousekeepingTaskData(
            hotelId: $reservation->hotel_id,
            roomId: $reservation->room_id,
            reservationId: $reservation->id,
            priority: HousekeepingPriority::Normal->value,
            taskType: HousekeepingTaskType::Checkout->value,
            instructions: __('Post-checkout cleaning for reservation :number.', ['number' => $reservation->number]),
        ));
    }

    public static function nextNumber(): string
    {
        $prefix = 'HK-'.now()->format('Ymd').'-';

        do {
            $number = $prefix.Str::upper(Str::random(4));
        } while (HousekeepingTask::query()->where('number', $number)->exists());

        return $number;
    }
}
