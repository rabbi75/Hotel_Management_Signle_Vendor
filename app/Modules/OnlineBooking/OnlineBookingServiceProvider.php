<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking;

use App\Http\Middleware\SetCurrentCompany;
use App\Http\Middleware\SetCurrentHotel;
use App\Modules\Api\Http\Middleware\LogsApiRequests;
use App\Modules\Api\Http\Middleware\RendersProblemResponses;
use App\Modules\Api\Http\Middleware\ResolveTokenCompany;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\OnlineBooking\Models\BookingPaymentMethod;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\OnlineBooking\Policies\BookingPaymentMethodPolicy;
use App\Modules\OnlineBooking\Policies\BookingSettingPolicy;
use App\Modules\OnlineBooking\Services\AvailabilityQueryService;
use App\Modules\OnlineBooking\Services\RateQuoteService;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;
use Illuminate\Support\Facades\Route;

class OnlineBookingServiceProvider extends ModuleServiceProvider
{
    /**
     * Public /book routes must stay at the site root, not under /admin.
     */
    protected bool $panelPrefixed = false;

    protected array $policies = [
        BookingSetting::class => BookingSettingPolicy::class,
        BookingPaymentMethod::class => BookingPaymentMethodPolicy::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(AvailabilityQueryService::class);
        $this->app->singleton(RateQuoteService::class);
    }

    protected function bootModule(): void
    {
        Hotel::resolveRelationUsing('bookingSetting', static fn (Hotel $hotel) => $hotel->hasOne(BookingSetting::class));

        $this->registerAdminRoutes();
        $this->registerNavigation();
        $this->registerPublicApiRoutes();
    }

    protected function registerAdminRoutes(): void
    {
        $file = $this->path('Routes/admin.php');

        if (! is_file($file)) {
            return;
        }

        $group = Route::middleware(['web']);

        if (single_vendor()) {
            $group = $group->prefix('admin');
        }

        $group->group($file);
    }

    protected function registerNavigation(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Reservations', 27)->items([
                NavigationItem::make('Payment methods', 'booking-payments.index')
                    ->icon('wallet')
                    ->permissions('online_booking.manage')
                    ->activeWhen('booking-payments.*')
                    ->order(30),
            ]),
        );
    }

    protected function registerPublicApiRoutes(): void
    {
        $file = $this->path('Routes/public-api.php');

        if (! is_file($file)) {
            return;
        }

        Route::prefix((string) config('saas.api.prefix'))
            ->as('api.v1.')
            ->middleware([
                'api',
                RendersProblemResponses::class,
                ResolveTokenCompany::class,
                'throttle:api',
                LogsApiRequests::class,
            ])
            ->withoutMiddleware([SetCurrentCompany::class, SetCurrentHotel::class])
            ->group($file);
    }
}
