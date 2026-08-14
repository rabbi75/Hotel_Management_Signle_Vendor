<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Middleware;

use App\Modules\Billing\Services\SubscriptionLimits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on the active workspace's plan including a feature.
 *
 * Applied as `plan.feature:ai`. Independent of the permission check that also
 * guards these routes: a member may hold `ai.use` yet be on a plan that does
 * not include the AI module, and vice versa.
 */
class EnsurePlanFeature
{
    public function __construct(protected SubscriptionLimits $limits) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // 402 Payment Required is the honest status: the request is well-formed
        // and authorised, but the plan does not cover it. The Inertia error
        // handler renders it, and the client can offer an upgrade CTA.
        abort_unless($this->limits->hasFeature($feature), 402, __('Your plan does not include this feature.'));

        return $next($request);
    }
}
