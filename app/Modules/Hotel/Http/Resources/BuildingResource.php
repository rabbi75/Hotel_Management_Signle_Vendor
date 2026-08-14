<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Resources;

use App\Modules\Hotel\Models\Building;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Building */
class BuildingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Building $building */
        $building = $this->resource;

        return [
            'id' => $building->id,
            'hotel_id' => $building->hotel_id,
            'hotel' => $building->relationLoaded('hotel') ? $building->hotel?->name : null,
            'name' => $building->name,
            'code' => $building->code,
            'description' => $building->description,
            'is_active' => $building->is_active,
            'floors_count' => isset($building->floors_count) ? (int) $building->floors_count : null,
            'created_at' => $building->created_at?->toIso8601String(),
        ];
    }
}