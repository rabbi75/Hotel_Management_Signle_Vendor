<?php

declare(strict_types=1);

namespace App\Modules\HotelReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Modules\HotelReports\DTOs\ReportPeriodData;
use App\Modules\HotelReports\Services\HotelDashboardService;
use App\Modules\HotelReports\Services\OperationsReportService;
use App\Modules\HotelReports\Services\OccupancyReportService;
use App\Modules\HotelReports\Services\RevenueReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HotelDashboardController extends Controller
{
    use ProvidesHotelOptions;

    public function __invoke(Request $request, HotelDashboardService $dashboard, OperationsReportService $operations): Response
    {
        Gate::authorize('hotel_reports.view');

        $hotelId = $request->filled('hotel_id')
            ? (int) $request->integer('hotel_id')
            : current_hotel_id();

        return Inertia::render('hotel-dashboard/index', [
            'snapshot' => $dashboard->snapshot($hotelId),
            'arrivals' => $operations->todayArrivals($hotelId),
            'hotels' => $this->hotelOptions(),
            'selectedHotelId' => $hotelId,
        ]);
    }
}
