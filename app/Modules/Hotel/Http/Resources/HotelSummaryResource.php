<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Resources;

use App\Modules\Hotel\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Hotel */
class HotelSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Hotel $hotel */
        $hotel = $this->resource;

        return [
            'id' => $hotel->id,
            'uuid' => $hotel->uuid,
            'name' => $hotel->name,
            'status' => $hotel->status->value,
            'status_label' => $hotel->status->label(),
            'is_active' => $hotel->is_active,
            'logo' => $hotel->logoUrl(),
        ];
    }
}