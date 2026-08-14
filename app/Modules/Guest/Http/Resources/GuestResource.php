<?php

declare(strict_types=1);

namespace App\Modules\Guest\Http\Resources;

use App\Modules\Guest\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guest */
class GuestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Guest $guest */
        $guest = $this->resource;

        return [
            'id' => $guest->id,
            'uuid' => $guest->uuid,
            'hotel_id' => $guest->hotel_id,
            'hotel' => $guest->relationLoaded('hotel') ? $guest->hotel?->name : null,
            'first_name' => $guest->first_name,
            'last_name' => $guest->last_name,
            'full_name' => $guest->fullName(),
            'gender' => $guest->gender?->value,
            'gender_label' => $guest->gender?->label(),
            'date_of_birth' => $guest->date_of_birth?->toDateString(),
            'phone' => $guest->phone,
            'email' => $guest->email,
            'address' => $guest->address,
            'city' => $guest->city,
            'country' => $guest->country,
            'nationality' => $guest->nationality,
            'id_type' => $guest->id_type,
            'id_number' => $guest->id_number,
            'emergency_contact_name' => $guest->emergency_contact_name,
            'emergency_contact_phone' => $guest->emergency_contact_phone,
            'notes' => $guest->notes,
            'is_vip' => $guest->is_vip,
            'is_blacklisted' => $guest->is_blacklisted,
            'reservations_count' => isset($guest->reservations_count) ? (int) $guest->reservations_count : null,
            'created_at' => $guest->created_at?->toIso8601String(),
        ];
    }
}
