<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Resources\V1;

use App\Modules\Hotel\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoomType
 */
class PublicRoomTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RoomType $type */
        $type = $this->resource;

        return [
            'id' => $type->id,
            'type' => 'room_type',
            'name' => $type->name,
            'code' => $type->code,
            'description' => $type->description,
            'base_price' => $type->base_price,
            'max_adults' => $type->max_adults,
            'max_children' => $type->max_children,
            'max_occupancy' => $type->max_occupancy,
            'bed_configuration' => $type->bed_configuration,
        ];
    }
}
