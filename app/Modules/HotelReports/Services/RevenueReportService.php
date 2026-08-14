<?php

declare(strict_types=1);

namespace App\Modules\HotelReports\Services;

use App\Modules\Folio\Enums\GuestPaymentStatus;
use App\Modules\Folio\Models\GuestPayment;
use App\Modules\HotelReports\DTOs\ReportPeriodData;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    /**
     * @return array<string, mixed>
     */
    public function generate(ReportPeriodData $period): array
    {
        $payments = GuestPayment::query()
            ->select(DB::raw('DATE(paid_at) as day'), DB::raw('SUM(amount) as total'))
            ->where('status', GuestPaymentStatus::Completed->value)
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $period->from->toDateString())
            ->whereDate('paid_at', '<=', $period->to->toDateString())
            ->when($period->hotelId !== null, static function ($query) use ($period): void {
                $query->whereHas('folio', static fn ($folio) => $folio->where('hotel_id', $period->hotelId));
            })
            ->groupBy('day')
            ->pluck('total', 'day');

        /** @var list<array{date: string, amount: int}> */
        $series = [];

        $range = CarbonPeriod::create($period->from, $period->to);

        foreach ($range as $date) {
            $day = CarbonImmutable::parse($date)->toDateString();

            $series[] = [
                'date' => $day,
                'amount' => (int) ($payments[$day] ?? 0),
            ];
        }

        $total = (int) collect($series)->sum('amount');

        return [
            'period' => $period->toQuery(),
            'currency' => (string) config('saas.billing.currency', 'USD'),
            'total' => $total,
            'series' => $series,
        ];
    }
}
