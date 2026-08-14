<?php

declare(strict_types=1);

use App\Modules\HotelReports\Http\Controllers\HotelDashboardController;
use App\Modules\HotelReports\Http\Controllers\HotelReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'plan.feature:hotel_reports'])->group(function (): void {
    Route::get('hotel/dashboard', HotelDashboardController::class)->name('hotel.dashboard');

    Route::get('hotel/reports/occupancy', [HotelReportController::class, 'occupancy'])->name('hotel-reports.occupancy');
    Route::get('hotel/reports/revenue', [HotelReportController::class, 'revenue'])->name('hotel-reports.revenue');
    Route::get('hotel/reports/operations', [HotelReportController::class, 'operations'])->name('hotel-reports.operations');
});
