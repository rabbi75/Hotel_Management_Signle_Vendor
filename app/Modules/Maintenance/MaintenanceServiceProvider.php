<?php

declare(strict_types=1);

namespace App\Modules\Maintenance;

use App\Modules\Maintenance\Models\MaintenanceRequest;
use App\Modules\Maintenance\Policies\MaintenanceRequestPolicy;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class MaintenanceServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        MaintenanceRequest::class => MaintenanceRequestPolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Operations', 26)->items([
                NavigationItem::make('Maintenance', 'maintenance.index')
                    ->icon('wrench')
                    ->permissions('maintenance.view')
                    ->feature('maintenance')
                    ->activeWhen('maintenance.*')
                    ->order(20),
            ]),
        );
    }
}
