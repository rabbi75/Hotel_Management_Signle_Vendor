<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\HotelData;
use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Models\Hotel;

class UpdateHotel
{
    public function handle(Hotel $hotel, HotelData $data): Hotel
    {
        $attributes = $data->toUpdateAttributes();

        if ($data->wasProvided('name') && $hotel->name !== $data->name) {
            $attributes['slug'] = CreateHotel::uniqueSlug($data->name, $hotel->id);
        }

        if ($data->wasProvided('status')) {
            $attributes['is_active'] = $data->status === HotelStatus::Active;
        } elseif ($data->wasProvided('is_active')) {
            $attributes['status'] = $data->isActive ? HotelStatus::Active : HotelStatus::Inactive;
        }

        $hotel->fill($attributes)->save();

        return $hotel;
    }
}