<?php

declare(strict_types=1);

namespace App\Modules\HotelReports;

use App\Modules\HotelReports\Services\HotelDashboardService;
use App\Modules\HotelReports\Services\OccupancyReportService;
use App\Modules\HotelReports\Services\OperationsReportService;
use App\Modules\HotelReports\Services\RevenueReportService;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class HotelReportsServiceProvider extends ModuleServiceProvider
{
    protected function registerModule(): void
    {
        $this->app->singleton(HotelDashboardService::class);
        $this->app->singleton(OccupancyReportService::class);
        $this->app->singleton(RevenueReportService::class);
        $this->app->singleton(OperationsReportService::class);
    }

    protected function bootModule(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Analytics', 30)->items([
                NavigationItem::make('Hotel dashboard', 'hotel.dashboard')
                    ->icon('layout-dashboard')
                    ->permissions('hotel_reports.view')
                    ->feature('hotel_reports')
                    ->activeWhen('hotel.dashboard')
                    ->order(10),

                NavigationItem::make('Occupancy report', 'hotel-reports.occupancy')
                    ->icon('chart-column')
                    ->permissions('hotel_reports.view')
                    ->feature('hotel_reports')
                    ->activeWhen('hotel-reports.occupancy')
                    ->order(20),

                NavigationItem::make('Revenue report', 'hotel-reports.revenue')
                    ->icon('trending-up')
                    ->permissions('hotel_reports.view')
                    ->feature('hotel_reports')
                    ->activeWhen('hotel-reports.revenue')
                    ->order(30),

                NavigationItem::make('Arrivals & departures', 'hotel-reports.operations')
                    ->icon('arrow-left-right')
                    ->permissions('hotel_reports.view')
                    ->feature('hotel_reports')
                    ->activeWhen('hotel-reports.operations')
                    ->order(40),
            ]),
        );
    }
}
