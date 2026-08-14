<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Resources;

use App\Modules\Hotel\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RoomType */
class RoomTypeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var RoomType $roomType */
        $roomType = $this->resource;

        return [
            'id' => $roomType->id,
            'hotel_id' => $roomType->hotel_id,
            'hotel' => $roomType->relationLoaded('hotel') ? $roomType->hotel?->name : null,
            'name' => $roomType->name,
            'code' => $roomType->code,
            'description' => $roomType->description,
            'base_price' => $roomType->base_price,
            'max_adults' => $roomType->max_adults,
            'max_children' => $roomType->max_children,
            'max_occupancy' => $roomType->max_occupancy,
            'bed_configuration' => $roomType->bed_configuration,
            'is_active' => $roomType->is_active,
            'facility_ids' => $roomType->relationLoaded('facilities')
                ? $roomType->facilities->pluck('id')->all()
                : [],
            'rooms_count' => isset($roomType->rooms_count) ? (int) $roomType->rooms_count : null,
            'created_at' => $roomType->created_at?->toIso8601String(),
        ];
    }
}