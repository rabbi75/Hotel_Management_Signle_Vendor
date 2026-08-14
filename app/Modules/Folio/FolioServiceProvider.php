<?php

declare(strict_types=1);

namespace App\Modules\Folio;

use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Folio\Models\GuestInvoice;
use App\Modules\Folio\Models\HotelService;
use App\Modules\Folio\Policies\GuestFolioPolicy;
use App\Modules\Folio\Policies\GuestInvoicePolicy;
use App\Modules\Folio\Policies\HotelServicePolicy;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class FolioServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        HotelService::class => HotelServicePolicy::class,
        GuestFolio::class => GuestFolioPolicy::class,
        GuestInvoice::class => GuestInvoicePolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->loadViewsFrom($this->path('Resources/views'), 'folio');

        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Finance', 29)->items([
                NavigationItem::make('Guest folios', 'folios.index')
                    ->icon('wallet')
                    ->permissions('folios.view')
                    ->feature('reservations')
                    ->activeWhen('folios.*')
                    ->order(10),

                NavigationItem::make('Guest invoices', 'guest-invoices.index')
                    ->icon('receipt')
                    ->permissions('guest_invoices.view')
                    ->feature('reservations')
                    ->activeWhen('guest-invoices.*')
                    ->order(20),

                NavigationItem::make('Hotel services', 'hotel-services.index')
                    ->icon('concierge-bell')
                    ->permissions('hotel_services.view')
                    ->feature('reservations')
                    ->activeWhen('hotel-services.*')
                    ->order(30),
            ]),
        );
    }
}
