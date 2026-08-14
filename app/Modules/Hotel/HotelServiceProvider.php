<?php

declare(strict_types=1);

namespace App\Modules\Hotel;

use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\Models\Company;
use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Building;
use App\Modules\Hotel\Models\Facility;
use App\Modules\Hotel\Models\Floor;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\Hotel\Policies\BedPolicy;
use App\Modules\Hotel\Policies\BuildingPolicy;
use App\Modules\Hotel\Policies\FacilityPolicy;
use App\Modules\Hotel\Policies\FloorPolicy;
use App\Modules\Hotel\Policies\HotelPolicy;
use App\Modules\Hotel\Policies\RoomPolicy;
use App\Modules\Hotel\Policies\RoomTypePolicy;
use App\Modules\Hotel\Services\RoomStatusSync;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;
use App\Support\Tenancy\CurrentHotel;

class HotelServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Hotel::class => HotelPolicy::class,
        Building::class => BuildingPolicy::class,
        Floor::class => FloorPolicy::class,
        RoomType::class => RoomTypePolicy::class,
        Room::class => RoomPolicy::class,
        Bed::class => BedPolicy::class,
        Facility::class => FacilityPolicy::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(CurrentHotel::class);
        $this->app->singleton(RoomStatusSync::class);
    }

    protected function bootModule(): void
    {
        $this->registerLimitResolvers();
        $this->registerNavigation();
    }

    protected function registerLimitResolvers(): void
    {
        SubscriptionLimits::resolveUsing(
            'hotels',
            static fn (Company $company): int => Hotel::query()
                ->withoutCompanyScope()
                ->where('company_id', $company->id)
                ->whereNull('deleted_at')
                ->count(),
        );

        SubscriptionLimits::resolveUsing(
            'rooms',
            static fn (Company $company): int => Room::query()
                ->withoutCompanyScope()
                ->where('company_id', $company->id)
                ->whereNull('deleted_at')
                ->count(),
        );
    }

    protected function registerNavigation(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Property Management', 25)->items([
                NavigationItem::make('Hotels', 'hotels.index')
                    ->icon('building-2')
                    ->permissions('hotels.view')
                    ->feature('hotel_management')
                    ->activeWhen('hotels.*')
                    ->order(10),

                NavigationItem::make('Buildings', 'buildings.index')
                    ->icon('boxes')
                    ->permissions('buildings.view')
                    ->feature('hotel_management')
                    ->activeWhen('buildings.*')
                    ->order(20),

                NavigationItem::make('Floors', 'floors.index')
                    ->icon('layers')
                    ->permissions('floors.view')
                    ->feature('hotel_management')
                    ->activeWhen('floors.*')
                    ->order(30),

                NavigationItem::make('Room types', 'room-types.index')
                    ->icon('shapes')
                    ->permissions('room_types.view')
                    ->feature('hotel_management')
                    ->activeWhen('room-types.*')
                    ->order(40),

                NavigationItem::make('Rooms', 'rooms.index')
                    ->icon('house')
                    ->permissions('rooms.view')
                    ->feature('hotel_management')
                    ->activeWhen('rooms.*')
                    ->order(50),

                NavigationItem::make('Beds', 'beds.index')
                    ->icon('bed-double')
                    ->permissions('beds.view')
                    ->feature('hotel_management')
                    ->activeWhen('beds.*')
                    ->order(60),

                NavigationItem::make('Facilities', 'facilities.index')
                    ->icon('sparkles')
                    ->permissions('facilities.view')
                    ->feature('hotel_management')
                    ->activeWhen('facilities.*')
                    ->order(70),
            ]),
        );
    }
}
