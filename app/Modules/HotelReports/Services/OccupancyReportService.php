<?php

declare(strict_types=1);

namespace App\Modules\HotelReports\Services;

use App\Modules\Hotel\Models\Room;
use App\Modules\HotelReports\DTOs\ReportPeriodData;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class OccupancyReportService
{
    /**
     * @return array<string, mixed>
     */
    public function generate(ReportPeriodData $period): array
    {
        $totalRooms = Room::query()
            ->where('is_active', true)
            ->when($period->hotelId !== null, static fn ($q) => $q->where('hotel_id', $period->hotelId))
            ->count();

        /** @var list<array{date: string, sold: int, total: int, rate: float}> */
        $series = [];

        $range = CarbonPeriod::create($period->from, $period->to);

        foreach ($range as $date) {
            $day = CarbonImmutable::parse($date)->toDateString();
            $sold = $this->roomsSoldOn($day, $period->hotelId);

            $series[] = [
                'date' => $day,
                'sold' => $sold,
                'total' => $totalRooms,
                'rate' => $totalRooms > 0 ? round(($sold / $totalRooms) * 100, 1) : 0.0,
            ];
        }

        $avgRate = count($series) > 0
            ? round(collect($series)->avg('rate'), 1)
            : 0.0;

        return [
            'period' => $period->toQuery(),
            'total_rooms' => $totalRooms,
            'average_occupancy' => $avgRate,
            'series' => $series,
        ];
    }

    protected function roomsSoldOn(string $date, ?int $hotelId): int
    {
        $query = Reservation::query()
            ->whereNotIn('status', [
                ReservationStatus::Cancelled->value,
                ReservationStatus::NoShow->value,
            ])
            ->where('check_in_date', '<=', $date)
            ->where('check_out_date', '>', $date);

        if ($hotelId !== null) {
            $query->where('hotel_id', $hotelId);
        }

        return (int) $query->count();
    }
}
