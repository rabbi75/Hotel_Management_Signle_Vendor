<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Resources;

use App\Modules\Hotel\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Facility */
class FacilityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Facility $facility */
        $facility = $this->resource;

        return [
            'id' => $facility->id,
            'hotel_id' => $facility->hotel_id,
            'hotel' => $facility->relationLoaded('hotel') ? $facility->hotel?->name : null,
            'name' => $facility->name,
            'code' => $facility->code,
            'description' => $facility->description,
            'icon' => $facility->icon,
            'is_active' => $facility->is_active,
            'created_at' => $facility->created_at?->toIso8601String(),
        ];
    }
}