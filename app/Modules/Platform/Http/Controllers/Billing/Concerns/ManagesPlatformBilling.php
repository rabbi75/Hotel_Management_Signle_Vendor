<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Billing\Concerns;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Platform\Models\Admin;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The guard and the query shape every console billing screen needs.
 *
 * Two things are deliberate here. Authorisation is checked against the `admin`
 * guard rather than through `Gate::authorize`, because the Billing policies are
 * typed to a tenant `User` and test `current_company_id()` — neither of which
 * exists in the console. And every query lifts `CompanyScope` explicitly:
 * the admin route group strips SetCurrentCompany, so the scope is already a
 * no-op, and relying on that would make a cross-tenant read look accidental
 * instead of intended.
 */
trait ManagesPlatformBilling
{
    protected function authorizeBillingRead(Request $request): void
    {
        $this->authorizePermission($request, 'platform.invoices.view');
    }

    protected function authorizeRevenueRead(Request $request): void
    {
        $this->authorizePermission($request, 'platform.revenue.view');
    }

    /**
     * Moving money — a refund, a retry, a grace extension — is a strictly
     * higher bar than reading the ledger.
     */
    protected function authorizeBillingWrite(Request $request): void
    {
        $this->authorizePermission($request, 'platform.billing.manage');
    }

    /**
     * @return Builder<Invoice>
     */
    protected function invoices(): Builder
    {
        return Invoice::query()->withoutGlobalScope(CompanyScope::class);
    }

    /**
     * @return Builder<Subscription>
     */
    protected function subscriptions(): Builder
    {
        return Subscription::query()->withoutGlobalScope(CompanyScope::class);
    }

    /**
     * @return Builder<Transaction>
     */
    protected function transactions(): Builder
    {
        return Transaction::query()->withoutGlobalScope(CompanyScope::class);
    }

    protected function authorizePermission(Request $request, string $permission): void
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin && $admin->can($permission), 403);
    }
}
