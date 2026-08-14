<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Resources;

use App\Modules\Hotel\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Room */
class RoomResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Room $room */
        $room = $this->resource;

        return [
            'id' => $room->id,
            'hotel_id' => $room->hotel_id,
            'hotel' => $room->relationLoaded('hotel') ? $room->hotel?->name : null,
            'building_id' => $room->building_id,
            'building' => $room->relationLoaded('building') ? $room->building?->name : null,
            'floor_id' => $room->floor_id,
            'floor' => $room->relationLoaded('floor') ? $room->floor?->name : null,
            'room_type_id' => $room->room_type_id,
            'room_type' => $room->relationLoaded('roomType') ? $room->roomType?->name : null,
            'number' => $room->number,
            'code' => $room->code,
            'description' => $room->description,
            'base_price' => $room->base_price,
            'max_occupancy' => $room->max_occupancy,
            'status' => $room->status->value,
            'status_label' => $room->status->label(),
            'status_color' => $room->status->color(),
            'is_active' => $room->is_active,
            'facility_ids' => $room->relationLoaded('facilities')
                ? $room->facilities->pluck('id')->all()
                : [],
            'beds_count' => isset($room->beds_count) ? (int) $room->beds_count : null,
            'created_at' => $room->created_at?->toIso8601String(),
        ];
    }
}