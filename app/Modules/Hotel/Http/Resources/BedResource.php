<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Resources;

use App\Modules\Hotel\Models\Bed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bed */
class BedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Bed $bed */
        $bed = $this->resource;

        return [
            'id' => $bed->id,
            'hotel_id' => $bed->hotel_id,
            'hotel' => $bed->relationLoaded('hotel') ? $bed->hotel?->name : null,
            'room_id' => $bed->room_id,
            'room' => $bed->relationLoaded('room') ? $bed->room?->number : null,
            'floor_id' => $bed->floor_id,
            'name' => $bed->name,
            'code' => $bed->code,
            'bed_type' => $bed->bed_type,
            'price' => $bed->price,
            'description' => $bed->description,
            'status' => $bed->status->value,
            'status_label' => $bed->status->label(),
            'status_color' => $bed->status->color(),
            'is_active' => $bed->is_active,
            'created_at' => $bed->created_at?->toIso8601String(),
        ];
    }
}