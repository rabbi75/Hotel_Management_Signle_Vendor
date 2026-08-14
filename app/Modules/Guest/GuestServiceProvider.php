<?php

declare(strict_types=1);

namespace App\Modules\Guest;

use App\Modules\Guest\Models\Guest;
use App\Modules\Guest\Policies\GuestPolicy;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class GuestServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Guest::class => GuestPolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Guests', 28)->items([
                NavigationItem::make('Guests', 'guests.index')
                    ->icon('users')
                    ->permissions('guests.view')
                    ->feature('reservations')
                    ->activeWhen('guests.*')
                    ->order(10),
            ]),
        );
    }
}
