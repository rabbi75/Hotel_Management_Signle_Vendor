<?php

declare(strict_types=1);

namespace App\Modules\Reservation;

use App\Modules\Api\Support\WebhookEventRegistry;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Reservation\Policies\ReservationPolicy;
use App\Modules\Reservation\Services\AvailabilityService;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class ReservationServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Reservation::class => ReservationPolicy::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(AvailabilityService::class);
    }

    protected function bootModule(): void
    {
        $this->registerWebhookEvents();

        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Reservations', 26)->items([
                NavigationItem::make('Reservations', 'reservations.index')
                    ->icon('calendar-days')
                    ->permissions('reservations.view')
                    ->feature('reservations')
                    ->activeWhen('reservations.index', 'reservations.create', 'reservations.show', 'reservations.edit')
                    ->order(10),

                NavigationItem::make('Availability calendar', 'reservations.calendar')
                    ->icon('chart-column')
                    ->permissions('reservations.calendar')
                    ->feature('reservations')
                    ->activeWhen('reservations.calendar')
                    ->order(20),
            ]),
        );
    }

    protected function registerWebhookEvents(): void
    {
        if (! class_exists(WebhookEventRegistry::class)) {
            return;
        }

        $this->app->make(WebhookEventRegistry::class)->registerMany('Reservations', [
            'reservation.created' => __('A reservation was created.'),
            'reservation.updated' => __('A reservation was updated.'),
            'reservation.cancelled' => __('A reservation was cancelled.'),
        ]);
    }
}
