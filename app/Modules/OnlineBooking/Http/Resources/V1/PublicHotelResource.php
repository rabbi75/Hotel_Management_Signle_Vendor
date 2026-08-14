<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Resources\V1;

use App\Modules\Hotel\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Hotel
 */
class PublicHotelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Hotel $hotel */
        $hotel = $this->resource;

        return [
            'id' => $hotel->uuid,
            'type' => 'hotel',
            'name' => $hotel->name,
            'slug' => $hotel->slug,
            'description' => $hotel->description,
            'city' => $hotel->city,
            'country' => $hotel->country,
            'currency' => $hotel->currency,
            'check_in_time' => $hotel->check_in_time,
            'check_out_time' => $hotel->check_out_time,
            'is_active' => $hotel->is_active,
        ];
    }
}
