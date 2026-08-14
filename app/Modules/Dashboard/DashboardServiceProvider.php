<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Dashboard\Widgets\QuickActionsWidget;
use App\Modules\Dashboard\Widgets\RecentActivityWidget;
use App\Modules\Dashboard\Widgets\RecentLoginsWidget;
use App\Modules\Dashboard\Widgets\RevenueChartWidget;
use App\Modules\Dashboard\Widgets\StatsOverviewWidget;
use App\Modules\Dashboard\Widgets\TasksWidget;
use App\Modules\Dashboard\Widgets\UserGrowthChartWidget;
use App\Modules\Dashboard\Widgets\WidgetRegistry;
use App\Modules\User\Models\User;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;
use Illuminate\Support\Facades\Broadcast;

class DashboardServiceProvider extends ModuleServiceProvider
{
    protected function registerModule(): void
    {
        $this->app->singleton(WidgetRegistry::class);
        $this->app->singleton(DashboardService::class);
    }

    protected function bootModule(): void
    {
        $this->registerWidgets();
        $this->registerNavigation();
        $this->registerBroadcastChannel();
    }

    protected function registerWidgets(): void
    {
        $this->app->make(WidgetRegistry::class)->registerMany([
            new StatsOverviewWidget,
            new QuickActionsWidget,
            new RevenueChartWidget,
            new UserGrowthChartWidget,
            new RecentActivityWidget,
            new RecentLoginsWidget,
            new TasksWidget,
        ]);
    }

    protected function registerNavigation(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Overview', 10)->items([
                NavigationItem::make('Dashboard', 'dashboard')
                    ->icon('layout-dashboard')
                    ->permissions('dashboard.view')
                    ->activeWhen('dashboard', 'dashboard.*')
                    ->order(10),
            ]),
        );
    }

    /**
     * Authorises the workspace channel {@see Events\DashboardStatsUpdated}
     * broadcasts on. Registered here rather than in routes/channels.php so the
     * channel travels with the module that publishes to it.
     */
    protected function registerBroadcastChannel(): void
    {
        Broadcast::channel('company.{companyId}', static function (User $user, int|string $companyId): bool {
            return $user->belongsToCompany((int) $companyId);
        });
    }
}
