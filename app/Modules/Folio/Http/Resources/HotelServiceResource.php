<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Resources;

use App\Modules\Folio\Models\HotelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HotelService */
class HotelServiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var HotelService $service */
        $service = $this->resource;

        return [
            'id' => $service->id,
            'hotel_id' => $service->hotel_id,
            'hotel' => $service->relationLoaded('hotel') ? $service->hotel?->name : null,
            'name' => $service->name,
            'code' => $service->code,
            'category' => $service->category->value,
            'category_label' => $service->category->label(),
            'price' => $service->price,
            'tax_rate' => $service->tax_rate,
            'description' => $service->description,
            'is_active' => $service->is_active,
            'created_at' => $service->created_at?->toIso8601String(),
        ];
    }
}
