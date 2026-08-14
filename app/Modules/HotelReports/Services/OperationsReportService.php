<?php

declare(strict_types=1);

namespace App\Modules\HotelReports\Services;

use App\Modules\HotelReports\DTOs\ReportPeriodData;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;

class OperationsReportService
{
    /**
     * @return array<string, mixed>
     */
    public function generate(ReportPeriodData $period): array
    {
        $from = $period->from->toDateString();
        $to = $period->to->toDateString();

        $base = Reservation::query()
            ->with(['guest', 'room', 'hotel'])
            ->when($period->hotelId !== null, static fn ($q) => $q->where('hotel_id', $period->hotelId));

        $arrivals = (clone $base)
            ->whereBetween('check_in_date', [$from, $to])
            ->whereIn('status', [
                ReservationStatus::Pending->value,
                ReservationStatus::Confirmed->value,
                ReservationStatus::CheckedIn->value,
                ReservationStatus::CheckedOut->value,
            ])
            ->orderBy('check_in_date')
            ->get()
            ->map(static fn (Reservation $r): array => [
                'id' => $r->id,
                'number' => $r->number,
                'guest' => $r->guest?->fullName(),
                'room' => $r->room?->number,
                'hotel' => $r->hotel?->name,
                'date' => $r->check_in_date?->toDateString(),
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
            ])
            ->values()
            ->all();

        $departures = (clone $base)
            ->whereBetween('check_out_date', [$from, $to])
            ->whereIn('status', [
                ReservationStatus::CheckedIn->value,
                ReservationStatus::CheckedOut->value,
            ])
            ->orderBy('check_out_date')
            ->get()
            ->map(static fn (Reservation $r): array => [
                'id' => $r->id,
                'number' => $r->number,
                'guest' => $r->guest?->fullName(),
                'room' => $r->room?->number,
                'hotel' => $r->hotel?->name,
                'date' => $r->check_out_date?->toDateString(),
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
            ])
            ->values()
            ->all();

        return [
            'period' => $period->toQuery(),
            'arrivals' => $arrivals,
            'departures' => $departures,
            'summary' => [
                'arrivals_count' => count($arrivals),
                'departures_count' => count($departures),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function todayArrivals(?int $hotelId = null): array
    {
        $period = new ReportPeriodData(
            from: CarbonImmutable::today(),
            to: CarbonImmutable::today(),
            hotelId: $hotelId ?? current_hotel_id(),
        );

        return $this->generate($period)['arrivals'];
    }
}
