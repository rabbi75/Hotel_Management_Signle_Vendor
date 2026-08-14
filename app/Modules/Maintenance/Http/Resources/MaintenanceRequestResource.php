<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Resources;

use App\Modules\Maintenance\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaintenanceRequest */
class MaintenanceRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var MaintenanceRequest $workOrder */
        $workOrder = $this->resource;

        return [
            'id' => $workOrder->id,
            'number' => $workOrder->number,
            'hotel_id' => $workOrder->hotel_id,
            'hotel' => $workOrder->relationLoaded('hotel') ? $workOrder->hotel?->name : null,
            'room_id' => $workOrder->room_id,
            'room' => $workOrder->relationLoaded('room') ? $workOrder->room?->number : null,
            'bed_id' => $workOrder->bed_id,
            'bed' => $workOrder->relationLoaded('bed') ? $workOrder->bed?->name : null,
            'title' => $workOrder->title,
            'description' => $workOrder->description,
            'category' => $workOrder->category->value,
            'category_label' => $workOrder->category->label(),
            'priority' => $workOrder->priority->value,
            'priority_label' => $workOrder->priority->label(),
            'priority_color' => $workOrder->priority->color(),
            'status' => $workOrder->status->value,
            'status_label' => $workOrder->status->label(),
            'status_color' => $workOrder->status->color(),
            'blocks_room' => $workOrder->blocks_room,
            'assigned_to' => $workOrder->assigned_to,
            'assignee' => $workOrder->relationLoaded('assignee') ? $workOrder->assignee?->name : null,
            'reporter' => $workOrder->relationLoaded('reporter') ? $workOrder->reporter?->name : null,
            'due_at' => $workOrder->due_at?->toIso8601String(),
            'started_at' => $workOrder->started_at?->toIso8601String(),
            'completed_at' => $workOrder->completed_at?->toIso8601String(),
            'resolution_notes' => $workOrder->resolution_notes,
            'is_open' => $workOrder->isOpen(),
            'created_at' => $workOrder->created_at?->toIso8601String(),
        ];
    }
}
