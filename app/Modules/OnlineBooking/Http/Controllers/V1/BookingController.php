<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Controllers\V1;

use App\Modules\Api\Http\Controllers\V1\ApiController;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\OnlineBooking\Actions\CreateOnlineBooking;
use App\Modules\OnlineBooking\DTOs\OnlineBookingData;
use App\Modules\OnlineBooking\Http\Requests\V1\StoreBookingRequest;
use App\Modules\OnlineBooking\Http\Resources\V1\BookingResource;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends ApiController
{
    public function __construct(protected CreateOnlineBooking $createBooking) {}

    public function store(StoreBookingRequest $request, string $uuid): JsonResponse
    {
        $this->requireAbility($request, 'write');
        Gate::authorize('create', BookingSetting::class);

        $hotel = Hotel::query()
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->whereHas('bookingSetting', static fn (Builder $query): Builder => $query->where('is_enabled', true))
            ->firstOrFail();

        /** @var array<string, mixed> $guest */
        $guest = $request->input('guest', []);

        $reservation = $this->createBooking->handle(new OnlineBookingData(
            hotelId: $hotel->id,
            roomTypeId: (int) $request->integer('room_type_id'),
            checkInDate: (string) $request->input('check_in_date'),
            checkOutDate: (string) $request->input('check_out_date'),
            guestFirstName: (string) ($guest['first_name'] ?? ''),
            guestLastName: (string) ($guest['last_name'] ?? ''),
            guestEmail: isset($guest['email']) ? (string) $guest['email'] : null,
            guestPhone: isset($guest['phone']) ? (string) $guest['phone'] : null,
            adults: (int) $request->input('adults', 1),
            children: (int) $request->input('children', 0),
            specialRequests: $request->input('special_requests') ?: null,
            externalReference: $request->input('external_reference') ?: null,
            channelMetadata: is_array($request->input('channel_metadata')) ? $request->input('channel_metadata') : [],
        ));

        return $this->item($request, $reservation->load(['guest', 'hotel', 'roomType']), BookingResource::class, 201);
    }

    public function show(Request $request, string $number): JsonResponse
    {
        $this->requireAbility($request, 'read');
        Gate::authorize('viewAny', BookingSetting::class);

        $reservation = Reservation::query()
            ->where('number', $number)
            ->with(['guest', 'hotel', 'roomType'])
            ->firstOrFail();

        Gate::authorize('view', $reservation);

        return $this->item($request, $reservation, BookingResource::class);
    }
}
