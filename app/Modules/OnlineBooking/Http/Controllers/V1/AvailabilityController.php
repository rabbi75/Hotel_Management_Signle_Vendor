<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Controllers\V1;

use App\Modules\Api\Http\Controllers\V1\ApiController;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\OnlineBooking\Services\AvailabilityQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AvailabilityController extends ApiController
{
    public function __construct(protected AvailabilityQueryService $availability) {}

    public function show(Request $request, string $uuid): JsonResponse
    {
        $this->requireAbility($request, 'read');
        Gate::authorize('viewAny', BookingSetting::class);

        $request->validate([
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'room_type_id' => ['nullable', 'integer'],
        ]);

        $hotel = Hotel::query()
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->whereHas('bookingSetting', static fn (Builder $query): Builder => $query->where('is_enabled', true))
            ->firstOrFail();

        $checkIn = CarbonImmutable::parse((string) $request->input('check_in_date'))->startOfDay();
        $checkOut = CarbonImmutable::parse((string) $request->input('check_out_date'))->startOfDay();
        $roomTypeId = $request->filled('room_type_id') ? (int) $request->integer('room_type_id') : null;

        $rows = $this->availability->forHotel($hotel, $checkIn, $checkOut, $roomTypeId);

        return new JsonResponse([
            'data' => [
                'hotel_id' => $hotel->uuid,
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'currency' => $hotel->currency,
                'room_types' => $rows,
            ],
        ]);
    }
}
