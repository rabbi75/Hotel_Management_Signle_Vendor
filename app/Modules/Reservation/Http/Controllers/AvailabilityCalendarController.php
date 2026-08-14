<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Models\Floor;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityCalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('calendar', Reservation::class);

        $hotelId = $request->integer('hotel_id') ?: current_hotel_id();
        $start = CarbonImmutable::parse($request->input('start', now()->startOfWeek()->toDateString()))->startOfDay();
        $end = CarbonImmutable::parse($request->input('end', $start->addDays(13)->toDateString()))->startOfDay();

        if ($end->lessThan($start)) {
            $end = $start->addDays(13);
        }

        if ($end->diffInDays($start) > 62) {
            $end = $start->addDays(30);
        }

        $roomsQuery = Room::query()
            ->with(['floor', 'roomType'])
            ->where('is_active', true)
            ->orderBy('number');

        if ($hotelId) {
            $roomsQuery->where('hotel_id', $hotelId);
        }

        if ($request->filled('floor_id')) {
            $roomsQuery->where('floor_id', $request->integer('floor_id'));
        }

        if ($request->filled('room_type_id')) {
            $roomsQuery->where('room_type_id', $request->integer('room_type_id'));
        }

        if ($request->filled('status')) {
            $roomsQuery->where('status', $request->string('status')->toString());
        }

        $rooms = $roomsQuery->get();
        $roomIds = $rooms->pluck('id')->all();

        $reservations = Reservation::query()
            ->with('guest')
            ->whereIn('room_id', $roomIds ?: [0])
            ->whereIn('status', [
                ReservationStatus::Pending->value,
                ReservationStatus::Confirmed->value,
                ReservationStatus::CheckedIn->value,
            ])
            ->where('check_in_date', '<=', $end->toDateString())
            ->where('check_out_date', '>=', $start->toDateString())
            ->get();

        $days = [];
        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $days[] = $cursor->toDateString();
        }

        return Inertia::render('reservations/calendar', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'days' => $days,
            'hotelId' => $hotelId,
            'filters' => [
                'hotel_id' => $hotelId,
                'floor_id' => $request->input('floor_id'),
                'room_type_id' => $request->input('room_type_id'),
                'status' => $request->input('status'),
            ],
            'hotels' => Hotel::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn ($n, $i): array => [(string) $i => (string) $n])->all(),
            'floors' => Floor::query()
                ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
                ->orderBy('floor_number')
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($n, $i): array => [(string) $i => (string) $n])
                ->all(),
            'roomTypes' => RoomType::query()
                ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($n, $i): array => [(string) $i => (string) $n])
                ->all(),
            'rooms' => $rooms->map(fn (Room $room): array => [
                'id' => $room->id,
                'number' => $room->number,
                'status' => $room->status->value,
                'status_label' => $room->status->label(),
                'status_color' => $room->status->color(),
                'floor' => $room->floor?->name,
                'room_type' => $room->roomType?->name,
            ])->values()->all(),
            'reservations' => $reservations->map(fn (Reservation $r): array => [
                'id' => $r->id,
                'number' => $r->number,
                'room_id' => $r->room_id,
                'guest' => $r->guest?->fullName(),
                'check_in_date' => $r->check_in_date?->toDateString(),
                'check_out_date' => $r->check_out_date?->toDateString(),
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'status_color' => $r->status->color(),
            ])->values()->all(),
            'canCreate' => Gate::allows('create', Reservation::class),
        ]);
    }
}
