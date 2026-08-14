<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Controllers\Concerns;

use App\Modules\Hotel\Models\Building;
use App\Modules\Hotel\Models\Floor;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Support\Tenancy\CurrentCompany;

trait ProvidesHotelOptions
{
    /**
     * @return array<string, string>
     */
    protected function hotelOptions(): array
    {
        return Hotel::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function buildingOptions(?int $hotelId = null): array
    {
        $query = Building::query()->where('is_active', true)->orderBy('name');

        if ($hotelId !== null) {
            $query->where('hotel_id', $hotelId);
        } elseif (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        return $query->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function floorOptions(?int $hotelId = null): array
    {
        $query = Floor::query()->where('is_active', true)->orderBy('floor_number');

        if ($hotelId !== null) {
            $query->where('hotel_id', $hotelId);
        } elseif (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        return $query->get()
            ->mapWithKeys(fn (Floor $floor): array => [(string) $floor->id => $floor->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function roomTypeOptions(?int $hotelId = null): array
    {
        $query = RoomType::query()->where('is_active', true)->orderBy('name');

        if ($hotelId !== null) {
            $query->where('hotel_id', $hotelId);
        } elseif (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        return $query->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function roomOptions(?int $hotelId = null): array
    {
        $query = Room::query()->where('is_active', true)->orderBy('number');

        if ($hotelId !== null) {
            $query->where('hotel_id', $hotelId);
        } elseif (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        return $query->pluck('number', 'id')
            ->mapWithKeys(fn ($number, $id): array => [(string) $id => (string) $number])
            ->all();
    }

    protected function resolveHotelId(?int $requested): ?int
    {
        return $requested ?? current_hotel_id();
    }

    /**
     * @return array<string, string>
     */
    protected function staffOptions(): array
    {
        $company = app(CurrentCompany::class)->get();

        if ($company === null) {
            return [];
        }

        return $company->members()
            ->orderBy('users.name')
            ->pluck('users.name', 'users.id')
            ->mapWithKeys(fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }
}