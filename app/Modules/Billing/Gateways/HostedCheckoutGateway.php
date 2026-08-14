<?php

declare(strict_types=1);

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\DTOs\GatewayInvoiceData;
use App\Modules\Billing\DTOs\GatewayPaymentMethodData;
use App\Modules\Billing\DTOs\GatewaySubscriptionData;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentGatewayConfig;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\GatewayConfigRepository;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The shared half of a hosted-checkout processor.
 *
 * Every driver below this class works the same way: send the customer to a page
 * the processor hosts, and verify server-side that they paid when they come
 * back. What differs between them is only the API call that opens that page,
 * the one that confirms it, refunds, webhooks, and a health check — which is
 * all a subclass has to write.
 *
 * The subscription *lifecycle* deliberately does not vary. Periods are computed
 * here and stored in the kit's own tables, and RenewSubscriptionsCommand drives
 * renewals, exactly as it does for the offline driver. Delegating recurrence to
 * ten different processors would mean ten different lifecycles to reconcile,
 * and several of the processors here have no subscription product at all.
 *
 * The trade-off, stated plainly: a customer cancelling at the processor does
 * not automatically cancel here. That is what `handleWebhook()` is for on the
 * processors that report it, and what the dunning queue catches otherwise.
 */
abstract class HostedCheckoutGateway implements PaymentGateway
{
    public function __construct(protected GatewayConfigRepository $configs) {}

    /*
    |--------------------------------------------------------------------------
    | Local subscription lifecycle
    |--------------------------------------------------------------------------
    */

    public function createCustomer(Company $company): string
    {
        // Derived rather than stored: nothing remote is created until the
        // customer actually reaches checkout, and the processors here identify
        // a payer by the checkout reference instead.
        return $this->name().'_cus_'.$company->uuid;
    }

    public function subscribe(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        ?string $paymentMethodId = null,
        ?int $trialDays = null,
    ): GatewaySubscriptionData {
        $now = CarbonImmutable::now();
        $trialDays ??= $plan->trial_days;
        $trialEndsAt = $trialDays > 0 ? $now->addDays($trialDays) : null;

        // A trial defers the first paid period, so the period starts when the
        // trial ends rather than today.
        $periodStart = $trialEndsAt ?? $now;

        return new GatewaySubscriptionData(
            gateway: $this->name(),
            gatewayId: $paymentMethodId ?? ($this->name().'_sub_'.$company->uuid.'_'.$plan->slug.'_'.$now->getTimestamp()),
            status: $trialEndsAt !== null ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            interval: $interval,
            trialEndsAt: $trialEndsAt,
            currentPeriodStart: $periodStart,
            currentPeriodEnd: $interval->advance($periodStart),
        );
    }

    public function swapPlan(Subscription $subscription, Plan $plan, BillingInterval $interval): GatewaySubscriptionData
    {
        $start = $subscription->current_period_start ?? CarbonImmutable::now();

        // The billing anchor is preserved: a swap changes what is charged, not
        // when. Proration is settled on the next invoice.
        return new GatewaySubscriptionData(
            gateway: $this->name(),
            gatewayId: $subscription->gateway_id,
            status: $subscription->onTrial() ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            interval: $interval,
            trialEndsAt: $subscription->trial_ends_at,
            currentPeriodStart: $start,
            currentPeriodEnd: $subscription->interval === $interval
                ? ($subscription->current_period_end ?? $interval->advance($start))
                : $interval->advance($start),
        );
    }

    public function cancel(Subscription $subscription, bool $immediately = false): GatewaySubscriptionData
    {
        $now = CarbonImmutable::now();

        return new GatewaySubscriptionData(
            gateway: $this->name(),
            gatewayId: $subscription->gateway_id,
            status: $immediately ? SubscriptionStatus::Canceled : $subscription->status,
            interval: $subscription->interval,
            trialEndsAt: $subscription->trial_ends_at,
            currentPeriodStart: $subscription->current_period_start,
            currentPeriodEnd: $subscription->current_period_end,
            cancelsAt: $immediately ? $now : ($subscription->current_period_end ?? $now),
            endedAt: $immediately ? $now : null,
        );
    }

    public function resume(Subscription $subscription): GatewaySubscriptionData
    {
        return new GatewaySubscriptionData(
            gateway: $this->name(),
            gatewayId: $subscription->gateway_id,
            status: $subscription->onTrial() ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            interval: $subscription->interval,
            trialEndsAt: $subscription->trial_ends_at,
            currentPeriodStart: $subscription->current_period_start,
            currentPeriodEnd: $subscription->current_period_end,
            cancelsAt: null,
            endedAt: null,
        );
    }

    /**
     * Most processors send the customer back with a redirect, so there is no
     * POST body to read a reference out of. The two that post a form back
     * override this.
     */
    public function referenceFromCallback(Request $request): ?string
    {
        return null;
    }

    /**
     * Hosted checkout collects the card on the processor's own page, so nothing
     * is vaulted here. A stored method would be a card detail this application
     * has no business holding.
     */
    public function addPaymentMethod(Company $company, string $token): GatewayPaymentMethodData
    {
        return new GatewayPaymentMethodData(
            gateway: $this->name(),
            gatewayId: $this->name().'_pm_'.substr(hash('sha256', $company->uuid.$token), 0, 24),
            type: 'hosted',
            brand: $this->name(),
            lastFour: null,
            holderName: $company->name,
        );
    }

    public function defaultPaymentMethod(Company $company): ?GatewayPaymentMethodData
    {
        return null;
    }

    /**
     * @return list<GatewayInvoiceData>
     */
    public function invoices(Company $company): array
    {
        return Invoice::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn (Invoice $invoice): GatewayInvoiceData => new GatewayInvoiceData(
                gateway: $this->name(),
                gatewayId: $invoice->gateway_id,
                number: $invoice->number,
                status: $invoice->status,
                total: $invoice->total,
                currency: $invoice->currency,
                issuedAt: $invoice->issued_at,
                paidAt: $invoice->paid_at,
            ))
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration and transport
    |--------------------------------------------------------------------------
    */

    protected function config(): PaymentGatewayConfig
    {
        $config = $this->configs->find($this->name());

        if (! $config instanceof PaymentGatewayConfig) {
            throw new BillingException("The [{$this->name()}] gateway has not been configured.");
        }

        return $config;
    }

    protected function isTestMode(): bool
    {
        return $this->config()->is_test_mode;
    }

    /**
     * A credential, or a refusal to proceed without it.
     *
     * Deliberately throws rather than returning null: a checkout that silently
     * continues with a missing key produces an opaque failure at the processor
     * instead of a message naming what is not set up.
     */
    protected function credential(string $key): string
    {
        $value = $this->config()->credential($key);

        if ($value === null) {
            throw new BillingException("The [{$this->name()}] gateway is missing its [{$key}] credential.");
        }

        return $value;
    }

    /**
     * The processor's base URL for the configured mode.
     */
    abstract protected function baseUrl(): string;

    /**
     * Shared transport: a timeout so a slow processor cannot hold a web worker
     * open, and one retry for a transient network fault. Not retried on a 4xx,
     * which would be re-sending a request the processor already rejected.
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl(), '/'))
            ->timeout(20)
            ->connectTimeout(10)
            // The `when` callback is what actually holds the "not on a 4xx"
            // rule: `retry()` on its own retries every failed response, and
            // re-sending a create-payment call the processor already rejected
            // is how one customer ends up with two charges.
            ->retry(2, 200, function (Throwable $exception): bool {
                if (! $exception instanceof RequestException) {
                    // A connection error or timeout — nothing reached the
                    // processor, so there is nothing to double.
                    return true;
                }

                $status = $exception->response->status();

                return $status >= 500 || $status === 429;
            }, throw: false)
            ->acceptJson()
            ->asJson();
    }

    /**
     * The absolute amount a plan costs on this gateway, in minor units.
     */
    protected function amountFor(Plan $plan, BillingInterval $interval): int
    {
        return $plan->priceFor($interval)->amount;
    }

    protected function currencyFor(Plan $plan): string
    {
        return strtoupper($plan->currency);
    }

    /**
     * Minor units to the major-unit decimal string most processors expect.
     */
    protected function major(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', '');
    }

    /**
     * Whether a captured amount is the one this checkout was actually for.
     *
     * The return leg names its plan and interval in a signed query string, so
     * what *should* have been charged is knowable here. Processors that hand
     * back a bare reference — one the customer's browser has seen and could
     * substitute for another — need this: without it, a reference replayed
     * from a cheaper completed payment would verify happily and buy the
     * expensive plan.
     *
     * A plan that no longer resolves cannot be checked against, and is allowed
     * through rather than failing a customer who has genuinely paid for a plan
     * an operator has since deleted.
     */
    protected function matchesExpectedCharge(Request $request, int $amount, string $currency): bool
    {
        $plan = Plan::query()->where('slug', $request->string('plan')->toString())->first();

        if (! $plan instanceof Plan) {
            return true;
        }

        $interval = BillingInterval::tryFrom($request->string('interval')->toString()) ?? BillingInterval::Monthly;

        return $amount === $this->amountFor($plan, $interval)
            && strtoupper($currency) === $this->currencyFor($plan);
    }
}
