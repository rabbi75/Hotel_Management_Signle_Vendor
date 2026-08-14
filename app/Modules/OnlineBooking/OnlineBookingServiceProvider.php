<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking;

use App\Http\Middleware\SetCurrentCompany;
use App\Http\Middleware\SetCurrentHotel;
use App\Modules\Api\Http\Middleware\LogsApiRequests;
use App\Modules\Api\Http\Middleware\RendersProblemResponses;
use App\Modules\Api\Http\Middleware\ResolveTokenCompany;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\OnlineBooking\Policies\BookingSettingPolicy;
use App\Modules\OnlineBooking\Services\AvailabilityQueryService;
use App\Modules\OnlineBooking\Services\RateQuoteService;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;

class OnlineBookingServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        BookingSetting::class => BookingSettingPolicy::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(AvailabilityQueryService::class);
        $this->app->singleton(RateQuoteService::class);
    }

    protected function bootModule(): void
    {
        Hotel::resolveRelationUsing('bookingSetting', static fn (Hotel $hotel) => $hotel->hasOne(BookingSetting::class));

        $this->registerPublicApiRoutes();
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
