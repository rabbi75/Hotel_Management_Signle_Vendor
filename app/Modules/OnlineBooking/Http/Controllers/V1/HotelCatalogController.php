<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Controllers\V1;

use App\Modules\Api\Http\Controllers\V1\ApiController;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\OnlineBooking\Http\Resources\V1\PublicHotelResource;
use App\Modules\OnlineBooking\Http\Resources\V1\PublicRoomTypeResource;
use App\Modules\OnlineBooking\Models\BookingSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HotelCatalogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'read');
        Gate::authorize('viewAny', BookingSetting::class);

        $query = Hotel::query()
            ->where('is_active', true)
            ->whereHas('bookingSetting', static fn (Builder $query): Builder => $query->where('is_enabled', true))
            ->with('bookingSetting')
            ->orderBy('name');

        return $this->collection($request, $query, PublicHotelResource::class);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $this->requireAbility($request, 'read');
        Gate::authorize('viewAny', BookingSetting::class);

        $hotel = $this->bookableHotel($uuid);

        return $this->item($request, $hotel, PublicHotelResource::class);
    }

    public function roomTypes(Request $request, string $uuid): JsonResponse
    {
        $this->requireAbility($request, 'read');
        Gate::authorize('viewAny', BookingSetting::class);

        $hotel = $this->bookableHotel($uuid);

        $query = RoomType::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name');

        return $this->collection($request, $query, PublicRoomTypeResource::class);
    }

    protected function bookableHotel(string $uuid): Hotel
    {
        return Hotel::query()
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->whereHas('bookingSetting', static fn (Builder $query): Builder => $query->where('is_enabled', true))
            ->firstOrFail();
    }
}
