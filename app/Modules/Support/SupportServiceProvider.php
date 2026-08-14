<?php

declare(strict_types=1);

namespace App\Modules\Support;

use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Policies\SupportTicketPolicy;
use App\Modules\Support\Services\TicketService;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class SupportServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        SupportTicket::class => SupportTicketPolicy::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(TicketService::class);
    }

    protected function bootModule(): void
    {
        if (single_vendor()) {
            return;
        }

        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Administration', 60)->items([
                NavigationItem::make('Support', 'support.index')
                    ->icon('life-buoy')
                    ->permissions('support.view')
                    ->activeWhen('support.*')
                    ->order(20),
            ]),
        );
    }
}
