<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Http\Resources;

use App\Modules\Housekeeping\Models\HousekeepingTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HousekeepingTask */
class HousekeepingTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var HousekeepingTask $task */
        $task = $this->resource;

        return [
            'id' => $task->id,
            'number' => $task->number,
            'hotel_id' => $task->hotel_id,
            'hotel' => $task->relationLoaded('hotel') ? $task->hotel?->name : null,
            'room_id' => $task->room_id,
            'room' => $task->relationLoaded('room') ? $task->room?->number : null,
            'reservation_id' => $task->reservation_id,
            'reservation' => $task->relationLoaded('reservation') ? $task->reservation?->number : null,
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'status_color' => $task->status->color(),
            'priority' => $task->priority->value,
            'priority_label' => $task->priority->label(),
            'priority_color' => $task->priority->color(),
            'task_type' => $task->task_type->value,
            'task_type_label' => $task->task_type->label(),
            'assigned_to' => $task->assigned_to,
            'assignee' => $task->relationLoaded('assignee') ? $task->assignee?->name : null,
            'instructions' => $task->instructions,
            'notes' => $task->notes,
            'scheduled_for' => $task->scheduled_for?->toDateString(),
            'started_at' => $task->started_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'is_open' => $task->isOpen(),
            'can_start' => $task->status->canStart(),
            'can_complete' => $task->status->canComplete() || $task->status->value === 'pending',
            'can_cancel' => $task->status->canCancel(),
            'created_at' => $task->created_at?->toIso8601String(),
        ];
    }
}
