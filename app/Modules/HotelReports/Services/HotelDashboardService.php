<?php

declare(strict_types=1);

namespace App\Modules\HotelReports\Services;

use App\Modules\Folio\Enums\GuestPaymentStatus;
use App\Modules\Folio\Models\GuestPayment;
use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Room;
use App\Modules\Housekeeping\Enums\HousekeepingTaskStatus;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Maintenance\Enums\MaintenanceRequestStatus;
use App\Modules\Maintenance\Models\MaintenanceRequest;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class HotelDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $hotelId = null): array
    {
        $hotelId ??= current_hotel_id();
        $today = CarbonImmutable::today()->toDateString();

        $rooms = Room::query()->where('is_active', true);
        if ($hotelId !== null) {
            $rooms->where('hotel_id', $hotelId);
        }

        $totalRooms = (clone $rooms)->count();

        $statusCounts = (clone $rooms)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $occupiedRooms = (int) (($statusCounts[RoomStatus::Occupied->value] ?? 0)
            + ($statusCounts[RoomStatus::Reserved->value] ?? 0));

        $availableRooms = (int) ($statusCounts[RoomStatus::Available->value] ?? 0);

        $occupancyRate = $totalRooms > 0
            ? round(($occupiedRooms / $totalRooms) * 100, 1)
            : 0.0;

        $reservations = Reservation::query();
        if ($hotelId !== null) {
            $reservations->where('hotel_id', $hotelId);
        }

        $inHouse = (clone $reservations)->where('status', ReservationStatus::CheckedIn)->count();

        $arrivalsToday = (clone $reservations)
            ->whereDate('check_in_date', $today)
            ->whereIn('status', [
                ReservationStatus::Pending->value,
                ReservationStatus::Confirmed->value,
                ReservationStatus::CheckedIn->value,
            ])
            ->count();

        $departuresToday = (clone $reservations)
            ->whereDate('check_out_date', $today)
            ->where('status', ReservationStatus::CheckedIn->value)
            ->count();

        $revenueToday = (int) GuestPayment::query()
            ->where('status', GuestPaymentStatus::Completed->value)
            ->whereDate('paid_at', $today)
            ->when($hotelId !== null, static function ($query) use ($hotelId): void {
                $query->whereHas('folio', static fn ($folio) => $folio->where('hotel_id', $hotelId));
            })
            ->sum('amount');

        $pendingHousekeeping = HousekeepingTask::query()
            ->whereIn('status', [
                HousekeepingTaskStatus::Pending->value,
                HousekeepingTaskStatus::InProgress->value,
            ])
            ->when($hotelId !== null, static fn ($q) => $q->where('hotel_id', $hotelId))
            ->count();

        $openMaintenance = MaintenanceRequest::query()
            ->whereIn('status', [
                MaintenanceRequestStatus::Open->value,
                MaintenanceRequestStatus::InProgress->value,
                MaintenanceRequestStatus::OnHold->value,
            ])
            ->when($hotelId !== null, static fn ($q) => $q->where('hotel_id', $hotelId))
            ->count();

        /** @var list<array{status: string, label: string, count: int, color: string}> */
        $roomStatusBreakdown = [];

        foreach (RoomStatus::cases() as $status) {
            $count = (int) ($statusCounts[$status->value] ?? 0);

            if ($count > 0) {
                $roomStatusBreakdown[] = [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'count' => $count,
                    'color' => $status->color(),
                ];
            }
        }

        return [
            'hotel_id' => $hotelId,
            'date' => $today,
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupiedRooms,
            'available_rooms' => $availableRooms,
            'occupancy_rate' => $occupancyRate,
            'in_house_guests' => $inHouse,
            'arrivals_today' => $arrivalsToday,
            'departures_today' => $departuresToday,
            'revenue_today' => $revenueToday,
            'currency' => (string) config('saas.billing.currency', 'USD'),
            'pending_housekeeping' => $pendingHousekeeping,
            'open_maintenance' => $openMaintenance,
            'room_status_breakdown' => $roomStatusBreakdown,
        ];
    }
}
