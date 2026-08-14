<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Middleware;

use App\Modules\Billing\Services\SubscriptionLimits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates booking API routes on both API access and the online booking feature.
 */
class EnsureOnlineBookingApi
{
    public function __construct(protected SubscriptionLimits $limits) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $this->limits->hasFeature('api') && $this->limits->hasFeature('online_booking'),
            402,
            __('Your plan does not include online booking API access.'),
        );

        return $next($request);
    }
}
