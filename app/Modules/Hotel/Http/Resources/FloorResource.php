<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Resources;

use App\Modules\Hotel\Models\Floor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Floor */
class FloorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Floor $floor */
        $floor = $this->resource;

        return [
            'id' => $floor->id,
            'hotel_id' => $floor->hotel_id,
            'hotel' => $floor->relationLoaded('hotel') ? $floor->hotel?->name : null,
            'building_id' => $floor->building_id,
            'building' => $floor->relationLoaded('building') ? $floor->building?->name : null,
            'name' => $floor->name,
            'floor_number' => $floor->floor_number,
            'code' => $floor->code,
            'description' => $floor->description,
            'is_active' => $floor->is_active,
            'rooms_count' => isset($floor->rooms_count) ? (int) $floor->rooms_count : null,
            'created_at' => $floor->created_at?->toIso8601String(),
        ];
    }
}