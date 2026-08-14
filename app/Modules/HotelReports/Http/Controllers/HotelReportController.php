<?php

declare(strict_types=1);

namespace App\Modules\HotelReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Modules\HotelReports\DTOs\ReportPeriodData;
use App\Modules\HotelReports\Services\OccupancyReportService;
use App\Modules\HotelReports\Services\OperationsReportService;
use App\Modules\HotelReports\Services\RevenueReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HotelReportController extends Controller
{
    use ProvidesHotelOptions;

    public function occupancy(Request $request, OccupancyReportService $report): Response
    {
        Gate::authorize('hotel_reports.view');

        $period = ReportPeriodData::fromRequest($request);

        return Inertia::render('hotel-reports/occupancy', [
            'report' => $report->generate($period),
            'hotels' => $this->hotelOptions(),
            'filters' => $period->toQuery(),
        ]);
    }

    public function revenue(Request $request, RevenueReportService $report): Response
    {
        Gate::authorize('hotel_reports.view');

        $period = ReportPeriodData::fromRequest($request);

        return Inertia::render('hotel-reports/revenue', [
            'report' => $report->generate($period),
            'hotels' => $this->hotelOptions(),
            'filters' => $period->toQuery(),
        ]);
    }

    public function operations(Request $request, OperationsReportService $report): Response
    {
        Gate::authorize('hotel_reports.view');

        $period = ReportPeriodData::fromRequest($request, 7);

        return Inertia::render('hotel-reports/operations', [
            'report' => $report->generate($period),
            'hotels' => $this->hotelOptions(),
            'filters' => $period->toQuery(),
        ]);
    }
}
