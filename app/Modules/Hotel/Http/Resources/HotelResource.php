<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Resources;

use App\Modules\Hotel\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Hotel */
class HotelResource extends JsonResource
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
            'slug' => $hotel->slug,
            'description' => $hotel->description,
            'address' => $hotel->address,
            'city' => $hotel->city,
            'state' => $hotel->state,
            'country' => $hotel->country,
            'postal_code' => $hotel->postal_code,
            'phone' => $hotel->phone,
            'email' => $hotel->email,
            'website' => $hotel->website,
            'check_in_time' => $hotel->check_in_time,
            'check_out_time' => $hotel->check_out_time,
            'currency' => $hotel->currency,
            'timezone' => $hotel->timezone,
            'tax_rate' => (float) $hotel->tax_rate,
            'tax_name' => $hotel->tax_name,
            'policies' => $hotel->policies,
            'contact_name' => $hotel->contact_name,
            'contact_phone' => $hotel->contact_phone,
            'contact_email' => $hotel->contact_email,
            'status' => $hotel->status->value,
            'status_label' => $hotel->status->label(),
            'status_color' => $hotel->status->color(),
            'is_active' => $hotel->is_active,
            'logo' => $hotel->logoUrl(),
            'cover' => $hotel->coverUrl(),
            'rooms_count' => $this->counter($hotel, 'rooms_count'),
            'created_at' => $hotel->created_at?->toIso8601String(),
        ];
    }

    protected function counter(Hotel $hotel, string $key): ?int
    {
        $value = $hotel->getAttributes()[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}