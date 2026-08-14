<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping;

use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Housekeeping\Policies\HousekeepingTaskPolicy;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class HousekeepingServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        HousekeepingTask::class => HousekeepingTaskPolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Operations', 26)->items([
                NavigationItem::make('Housekeeping', 'housekeeping.index')
                    ->icon('brush')
                    ->permissions('housekeeping.view')
                    ->feature('housekeeping')
                    ->activeWhen('housekeeping.*')
                    ->order(10),
            ]),
        );
    }
}
