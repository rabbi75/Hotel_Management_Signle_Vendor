<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Middleware;

use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks a workspace out of the app unless its plan grants access.
 *
 * This is the pure-SaaS gate: a workspace may use the app only while
 * {@see Company::hasActiveAccess()} holds — a live
 * subscription (trialing, active, or past-due within grace) or an unexpired
 * signup trial. A workspace whose trial lapsed, whose subscription was
 * cancelled, or that never chose a plan is redirected to the plan picker. New
 * workspaces are auto-subscribed to a trial (see CreateCompany), so this only
 * bites once that trial ends without payment. Never fires when billing is
 * disabled, so a kit without a processor is unaffected.
 */
class EnsureSubscriptionActive
{
    /**
     * Routes always reachable, so a locked-out tenant can still pay, leave, get
     * help, or switch to a workspace that is in good standing.
     *
     * @var list<string>
     */
    protected array $allowlist = [
        'billing.*',
        'settings.*',
        'profile.*',
        'companies.switch',
        'companies.index',
        'logout',
        'admin.*',
        'notifications.*',
    ];

    public function __construct(protected CurrentCompany $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldLock()) {
            return $next($request);
        }

        return redirect()
            ->route('billing.plans')
            ->with('warning', __('Choose a plan to continue.'));
    }

    protected function shouldLock(): bool
    {
        // No tenant (guest, admin panel), billing off, or an allowlisted route:
        // never lock.
        if (! $this->tenant->has() || ! (bool) config('saas.billing.enabled', false)) {
            return false;
        }

        if ($this->routeIsAllowed()) {
            return false;
        }

        // Pure SaaS: a workspace may only use the app while its plan grants
        // access — a live subscription (trialing, active, past-due in grace) or an
        // unexpired signup trial. Everything else — trial lapsed, subscription
        // cancelled, or no plan ever chosen — is locked to the plan picker.
        $company = $this->tenant->get();

        return $company !== null && ! $company->hasActiveAccess();
    }

    protected function routeIsAllowed(): bool
    {
        $name = Route::currentRouteName();

        if ($name === null) {
            return true;
        }

        foreach ($this->allowlist as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
