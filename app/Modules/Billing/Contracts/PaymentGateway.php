<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\DTOs\CheckoutResult;
use App\Modules\Billing\DTOs\CheckoutSession;
use App\Modules\Billing\DTOs\GatewayInvoiceData;
use App\Modules\Billing\DTOs\GatewayPaymentMethodData;
use App\Modules\Billing\DTOs\GatewayRefundData;
use App\Modules\Billing\DTOs\GatewaySubscriptionData;
use App\Modules\Billing\DTOs\GatewayWebhookData;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Company\Models\Company;
use Illuminate\Http\Request;

/**
 * The seam between the kit and a payment processor.
 *
 * Implementations describe remote state and return DTOs; they never persist.
 * The kit's own tables stay the source of truth, so a processor can be replaced
 * without a data migration.
 */
interface PaymentGateway
{
    /**
     * The driver key, matching `config('saas.billing.gateway')`.
     */
    public function name(): string;

    /**
     * Ensure a customer record exists for the workspace and return its id.
     */
    public function createCustomer(Company $company): string;

    /**
     * Open a hosted checkout for one plan purchase.
     *
     * Returns where to send the customer and the processor's own reference for
     * the attempt. The kit records nothing at this point: an abandoned checkout
     * must leave no trace of a subscription that was never paid for.
     *
     * @param  string  $returnUrl  Where the processor sends the customer back to.
     */
    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession;

    /**
     * Establish, server-side, whether a returning customer actually paid.
     *
     * The return leg is a URL the customer's own browser was redirected to, so
     * its query string is input and nothing more. Implementations re-fetch the
     * reference from the processor (or verify a signature) and must never infer
     * success from the request alone.
     *
     * Returning null means "could not be verified" — distinct from a result
     * with `paid: false`, which means the processor said it did not go through.
     */
    public function verifyCheckout(Request $request): ?CheckoutResult;

    /**
     * Pull the processor's own reference out of a POSTed return leg.
     *
     * Some processors send the customer back with a form POST rather than a
     * redirect, which arrives cross-site and therefore without a session
     * cookie. Those land on the stateless bridge instead, which asks the driver
     * for the reference and then redirects the browser — as a top-level GET,
     * which does carry the session — to the ordinary signed return URL.
     *
     * Returns null on the drivers whose customers come back by GET, which is
     * most of them.
     */
    public function referenceFromCallback(Request $request): ?string;

    /**
     * A cheap authenticated call, for the console's "test connection" button.
     * Returns null on success, or the processor's own error message.
     */
    public function ping(): ?string;

    public function subscribe(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        ?string $paymentMethodId = null,
        ?int $trialDays = null,
    ): GatewaySubscriptionData;

    public function swapPlan(Subscription $subscription, Plan $plan, BillingInterval $interval): GatewaySubscriptionData;

    /**
     * @param  bool  $immediately  End now rather than at the period boundary.
     */
    public function cancel(Subscription $subscription, bool $immediately = false): GatewaySubscriptionData;

    /**
     * Undo a pending cancellation. Only valid while the period has not lapsed.
     */
    public function resume(Subscription $subscription): GatewaySubscriptionData;

    /**
     * @param  string  $token  A gateway-side token; never raw card details.
     */
    public function addPaymentMethod(Company $company, string $token): GatewayPaymentMethodData;

    public function defaultPaymentMethod(Company $company): ?GatewayPaymentMethodData;

    /**
     * @return list<GatewayInvoiceData>
     */
    public function invoices(Company $company): array;

    /**
     * Return money for a settled charge.
     *
     * @param  int|null  $amount  Minor units to refund; null means the full
     *                            charge. A partial refund is the common case in
     *                            support, so it is not an afterthought here.
     */
    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData;

    /**
     * Verify and normalise an inbound webhook.
     *
     * Returning null means "not for me, or not verifiable" — the controller
     * answers 400 rather than trusting the body.
     */
    public function handleWebhook(Request $request): ?GatewayWebhookData;
}
