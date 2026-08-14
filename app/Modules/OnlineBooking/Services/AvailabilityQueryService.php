<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Services;

use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-side availability for the public booking API.
 *
 * Counts bookable physical rooms minus overlapping stays and unassigned
 * room-type reservations for the requested window.
 */
class AvailabilityQueryService
{
    /**
     * @return list<ReservationStatus>
     */
    protected function occupyingStatuses(): array
    {
        return array_values(array_filter(
            ReservationStatus::cases(),
            static fn (ReservationStatus $status): bool => $status->occupiesInventory(),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forHotel(
        Hotel $hotel,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $roomTypeId = null,
    ): array {
        /** @var Collection<int, RoomType> $types */
        $types = RoomType::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->when($roomTypeId !== null, static fn ($query) => $query->whereKey($roomTypeId))
            ->orderBy('name')
            ->get();

        $nights = max(1, $checkIn->diffInDays($checkOut));
        $rows = [];

        foreach ($types as $type) {
            $available = $this->availableCount($hotel->id, $type->id, $checkIn, $checkOut);

            if ($available <= 0) {
                continue;
            }

            $nightly = $type->base_price;
            $subtotal = $nightly * $nights;

            $rows[] = [
                'room_type_id' => $type->id,
                'room_type' => $type->name,
                'code' => $type->code,
                'available_rooms' => $available,
                'nightly_rate' => $nightly,
                'nights' => $nights,
                'subtotal' => $subtotal,
                'currency' => $hotel->currency,
            ];
        }

        return $rows;
    }

    public function availableCount(
        int $hotelId,
        int $roomTypeId,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
    ): int {
        /** @var Collection<int, int> $roomIds */
        $roomIds = Room::query()
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->whereNotIn('status', [RoomStatus::Maintenance->value, RoomStatus::OutOfService->value])
            ->pluck('id');

        if ($roomIds->isEmpty()) {
            return 0;
        }

        $occupiedRoomIds = Reservation::query()
            ->whereIn('room_id', $roomIds)
            ->whereIn('status', $this->occupyingStatuses())
            ->where('check_in_date', '<', $checkOut->toDateString())
            ->where('check_out_date', '>', $checkIn->toDateString())
            ->pluck('room_id')
            ->unique();

        $freeRooms = $roomIds->diff($occupiedRoomIds)->count();

        $unassigned = Reservation::query()
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->whereNull('room_id')
            ->whereIn('status', $this->occupyingStatuses())
            ->where('check_in_date', '<', $checkOut->toDateString())
            ->where('check_out_date', '>', $checkIn->toDateString())
            ->count();

        return max(0, $freeRooms - $unassigned);
    }
}
