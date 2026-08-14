<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\DTOs\CheckoutSession;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\PaymentGatewayConfig;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Open a hosted checkout for a plan purchase.
 *
 * Nothing is written here. A customer who opens a checkout and abandons it must
 * leave no subscription, no invoice and no transaction behind — the intent is
 * carried in a signed return URL instead, and only the return leg records
 * anything.
 *
 * Signing the return URL is what stops someone hand-crafting
 * `/billing/checkout/manual/return?plan=enterprise` and granting themselves a
 * plan they never paid for. The signature proves the parameters are ours; the
 * driver's own verification proves the money moved.
 */
class StartCheckout
{
    public function __construct(protected GatewayManager $gateways) {}

    public function handle(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        ?string $gateway = null,
        ?string $couponCode = null,
    ): CheckoutSession {
        if ($this->liveSubscription($company) instanceof Subscription) {
            throw new BillingException(__('This workspace already has an active subscription.'));
        }

        if (! $plan->is_active) {
            throw new BillingException(__('That plan is not available.'));
        }

        $driver = $this->resolveDriver($plan, $gateway);

        $parameters = [
            'gateway' => $driver,
            'plan' => $plan->slug,
            'interval' => $interval->value,
            'company' => $company->uuid,
        ];

        if ($couponCode !== null && $couponCode !== '') {
            $parameters['coupon'] = $couponCode;
        }

        $returnUrl = URL::temporarySignedRoute('billing.checkout.return', now()->addHour(), $parameters);
        $cancelUrl = URL::route('billing.plans');

        return $this->gateways->driver($driver)->checkout($company, $plan, $interval, $returnUrl, $cancelUrl);
    }

    /**
     * Whether any processor at all can take money for this plan.
     */
    public function hasOffer(Plan $plan): bool
    {
        return $this->gateways->enabledFor($plan)->isNotEmpty();
    }

    /**
     * @return Collection<string, PaymentGatewayConfig>
     */
    public function offers(Plan $plan): Collection
    {
        return $this->gateways->enabledFor($plan);
    }

    /**
     * The requested gateway if it is genuinely on offer for this plan, and the
     * default otherwise.
     *
     * A gateway named in the request is re-checked against the same rules the
     * picker was built from — the form field is customer input, and a disabled
     * or currency-mismatched processor must not become reachable by editing it.
     */
    protected function resolveDriver(Plan $plan, ?string $requested): string
    {
        $offered = $this->gateways->enabledFor($plan);

        if ($requested !== null && $offered->has($requested)) {
            return $requested;
        }

        $first = $offered->keys()->first();

        if (is_string($first)) {
            return $first;
        }

        // Nothing configured: fall back so a fresh installation can still take
        // the offline path rather than dead-ending at the plan picker.
        return $this->gateways->defaultDriver();
    }

    protected function liveSubscription(Company $company): ?Subscription
    {
        return Subscription::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->live()
            ->first();
    }
}
