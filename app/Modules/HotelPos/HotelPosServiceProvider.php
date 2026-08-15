<?php

declare(strict_types=1);

namespace App\Modules\HotelPos;

use App\Modules\HotelPos\Models\Restaurant;
use App\Modules\HotelPos\Policies\RestaurantPolicy;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class HotelPosServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Restaurant::class => RestaurantPolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Operations', 27)->items([
                NavigationItem::make('Restaurants', 'restaurants.index')
                    ->icon('utensils')
                    ->permissions('hotel_pos.view')
                    ->feature('hotel_pos')
                    ->activeWhen('restaurants.*')
                    ->order(40),
            ]),
        );
    }
}
