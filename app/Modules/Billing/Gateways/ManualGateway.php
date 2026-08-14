<?php

declare(strict_types=1);

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\DTOs\CheckoutResult;
use App\Modules\Billing\DTOs\CheckoutSession;
use App\Modules\Billing\DTOs\GatewayPaymentMethodData;
use App\Modules\Billing\DTOs\GatewayRefundData;
use App\Modules\Billing\DTOs\GatewaySubscriptionData;
use App\Modules\Billing\DTOs\GatewayWebhookData;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Company\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The offline driver, and the kit's default.
 *
 * It contacts nothing: periods are computed locally and every record lives in
 * the kit's own tables. That makes the whole billing surface usable — and
 * testable — with no credentials configured, which is what a starter kit needs
 * on first run and what CI needs on every run.
 *
 * It shares {@see HostedCheckoutGateway}'s lifecycle rather than repeating it,
 * because that lifecycle *is* this driver's behaviour: the hosted drivers were
 * modelled on it, not the other way round.
 */
class ManualGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'manual';
    }

    /**
     * There is no page to send anyone to, so the "checkout" is the return leg
     * itself. Keeping the same shape as the hosted drivers means the offline
     * path exercises the identical code in tests.
     */
    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        // The return URL is signed, and the signature covers the whole query
        // string — appending a reference to it would invalidate the very
        // signature that makes the return leg trustworthy. The offline driver
        // has no processor reference to carry anyway, so it sends the customer
        // exactly where it was told to.
        return new CheckoutSession(
            gateway: $this->name(),
            url: $returnUrl,
            reference: 'manual_ref_'.Str::lower(Str::random(24)),
        );
    }

    /**
     * Nothing was captured, so nothing is claimed to have been: the result is
     * unpaid, and the invoice it produces stays open until an operator records
     * payment from the collections screen.
     */
    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $reference = $request->query('reference');
        $reference = is_string($reference) && $reference !== ''
            ? $reference
            : 'manual_ref_'.Str::lower(Str::random(24));

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $reference,
            paid: false,
            amount: 0,
            currency: (string) config('saas.billing.currency', 'USD'),
            transactionId: $reference,
            failureReason: 'awaiting_offline_payment',
        );
    }

    /**
     * Records the refund without contacting anyone.
     *
     * The offline driver has no processor to reverse a charge at, so the refund
     * is an accounting entry and the operator moves the money by whatever means
     * they took it. Consistent with how the rest of this driver behaves: the
     * kit's tables are the whole truth.
     */
    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        return new GatewayRefundData(
            gateway: $this->name(),
            gatewayId: 'manual_re_'.$transaction->id.'_'.CarbonImmutable::now()->getTimestamp(),
            amount: $amount ?? $transaction->amount,
            currency: $transaction->currency,
            processedAt: CarbonImmutable::now(),
        );
    }

    public function addPaymentMethod(Company $company, string $token): GatewayPaymentMethodData
    {
        return new GatewayPaymentMethodData(
            gateway: $this->name(),
            gatewayId: 'manual_pm_'.substr(hash('sha256', $company->uuid.$token), 0, 24),
            type: 'offline',
            brand: 'offline',
            lastFour: null,
            holderName: $company->name,
        );
    }

    /**
     * There is no processor to sign an event, so there is nothing that can be
     * verified — and an unverifiable webhook must never be trusted.
     */
    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        return null;
    }

    /** Always reachable: there is nothing to reach. */
    public function ping(): ?string
    {
        return null;
    }

    /**
     * Convenience for the renewal command: what the next period looks like once
     * the current one lapses.
     */
    public function renew(Subscription $subscription): GatewaySubscriptionData
    {
        $start = $subscription->current_period_end ?? CarbonImmutable::now();

        return new GatewaySubscriptionData(
            gateway: $this->name(),
            gatewayId: $subscription->gateway_id,
            status: SubscriptionStatus::Active,
            interval: $subscription->interval,
            trialEndsAt: $subscription->trial_ends_at,
            currentPeriodStart: $start,
            currentPeriodEnd: $subscription->interval->advance($start),
        );
    }

    /**
     * The status a freshly generated manual invoice should carry: nothing is
     * captured automatically, so it is open until someone records payment.
     */
    public function defaultInvoiceStatus(): InvoiceStatus
    {
        return InvoiceStatus::Open;
    }

    protected function baseUrl(): string
    {
        // Never used — this driver makes no HTTP calls.
        return '';
    }
}
