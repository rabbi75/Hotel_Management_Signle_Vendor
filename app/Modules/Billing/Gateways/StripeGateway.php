<?php

declare(strict_types=1);

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\DTOs\CheckoutResult;
use App\Modules\Billing\DTOs\CheckoutSession;
use App\Modules\Billing\DTOs\GatewayInvoiceData;
use App\Modules\Billing\DTOs\GatewayPaymentMethodData;
use App\Modules\Billing\DTOs\GatewayRefundData;
use App\Modules\Billing\DTOs\GatewaySubscriptionData;
use App\Modules\Billing\DTOs\GatewayWebhookData;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Billing\Services\GatewayConfigRepository;
use App\Modules\Company\Models\Company;
use App\Support\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

/**
 * The Stripe driver, built on the client Cashier configures.
 *
 * Cashier's Billable trait is deliberately not mixed into the workspace model:
 * the kit's own tables are the record of truth, and adopting the trait would
 * put a second, gateway-shaped copy of subscription state on Company. The
 * customer id is instead kept in the scoped settings store, which is already
 * per-workspace and already cached.
 */
class StripeGateway implements PaymentGateway
{
    private const CUSTOMER_KEY = 'billing.stripe_customer_id';

    public function __construct(
        protected SettingsRepository $settings,
        protected GatewayConfigRepository $configs,
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    /**
     * Open a Stripe Checkout session for one plan purchase.
     *
     * Uses a configured price id when the plan carries one, and falls back to
     * `price_data` built from the plan's own amount — so an installation can
     * sell without mirroring its catalogue into Stripe first.
     */
    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $priceId = $plan->gatewayPrice($this->name(), $interval);

        $lineItem = $priceId !== null
            ? ['price' => $priceId, 'quantity' => 1]
            : [
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($plan->currency),
                    'unit_amount' => $plan->priceFor($interval)->amount,
                    'product_data' => ['name' => $plan->name],
                ],
            ];

        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [$lineItem],
                'customer' => $this->createCustomer($company),
                'success_url' => $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => ['company' => $company->uuid, 'plan' => $plan->slug],
            ]);
        } catch (Throwable $exception) {
            throw new BillingException($exception->getMessage());
        }

        $url = $session->url;

        if (! is_string($url)) {
            throw new BillingException(__('Stripe returned an unusable checkout.'));
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $session->id);
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $id = $request->query('session_id') ?? $request->query('reference');

        if (! is_string($id) || $id === '') {
            return null;
        }

        try {
            // Re-fetched from Stripe rather than read off the query string: the
            // customer's browser is not a trustworthy source for "this is paid".
            $session = $this->client()->checkout->sessions->retrieve($id, ['expand' => ['payment_intent']]);
        } catch (Throwable) {
            return null;
        }

        $intent = $session->payment_intent;
        $chargeId = is_object($intent) && isset($intent->latest_charge) && is_string($intent->latest_charge)
            ? $intent->latest_charge
            : (is_string($intent) ? $intent : $id);

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $id,
            paid: $session->payment_status === 'paid',
            amount: (int) ($session->amount_total ?? 0),
            currency: strtoupper((string) ($session->currency ?? 'usd')),
            transactionId: $chargeId,
            customerId: is_string($session->customer) ? $session->customer : null,
            failureReason: $session->payment_status === 'paid' ? null : (string) $session->payment_status,
        );
    }

    /**
     * Stripe redirects the customer back; there is no POSTed form to read.
     */
    public function referenceFromCallback(Request $request): ?string
    {
        return null;
    }

    public function ping(): ?string
    {
        try {
            $this->client()->balance->retrieve();
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function createCustomer(Company $company): string
    {
        $existing = $this->customerId($company);

        if ($existing !== null) {
            return $existing;
        }

        $customer = $this->client()->customers->create([
            'name' => $company->name,
            'email' => $company->email,
            'metadata' => ['company_uuid' => $company->uuid],
        ]);

        $this->settings->set(
            self::CUSTOMER_KEY,
            $customer->id,
            SettingsRepository::SCOPE_COMPANY,
            $company->id,
        );

        return $customer->id;
    }

    public function subscribe(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        ?string $paymentMethodId = null,
        ?int $trialDays = null,
    ): GatewaySubscriptionData {
        $price = $plan->gatewayPrice($this->name(), $interval);

        if ($price === null) {
            throw new BillingException("Plan [{$plan->slug}] has no Stripe price for the {$interval->value} interval.");
        }

        $payload = [
            'customer' => $this->createCustomer($company),
            'items' => [['price' => $price]],
            'metadata' => ['plan_slug' => $plan->slug, 'company_uuid' => $company->uuid],
            'expand' => ['items'],
        ];

        $trialDays ??= $plan->trial_days;

        if ($trialDays > 0) {
            $payload['trial_period_days'] = $trialDays;
        }

        if ($paymentMethodId !== null) {
            $payload['default_payment_method'] = $paymentMethodId;
        }

        return $this->toSubscriptionData($this->client()->subscriptions->create($payload)->toArray(), $interval);
    }

    public function swapPlan(Subscription $subscription, Plan $plan, BillingInterval $interval): GatewaySubscriptionData
    {
        $price = $plan->gatewayPrice($this->name(), $interval);
        $remoteId = $subscription->gateway_id;

        if ($price === null || $remoteId === null) {
            throw new BillingException('Cannot swap a subscription that is not linked to a Stripe price.');
        }

        $remote = $this->client()->subscriptions->retrieve($remoteId, ['expand' => ['items']]);
        $itemId = $remote->toArray()['items']['data'][0]['id'] ?? null;

        $updated = $this->client()->subscriptions->update($remoteId, [
            'items' => is_string($itemId)
                ? [['id' => $itemId, 'price' => $price]]
                : [['price' => $price]],
            'proration_behavior' => 'create_prorations',
            'expand' => ['items'],
        ]);

        return $this->toSubscriptionData($updated->toArray(), $interval);
    }

    public function cancel(Subscription $subscription, bool $immediately = false): GatewaySubscriptionData
    {
        $remoteId = $subscription->gateway_id;

        if ($remoteId === null) {
            throw new BillingException('Cannot cancel a subscription that has no Stripe id.');
        }

        $remote = $immediately
            ? $this->client()->subscriptions->cancel($remoteId, [])
            : $this->client()->subscriptions->update($remoteId, ['cancel_at_period_end' => true]);

        return $this->toSubscriptionData($remote->toArray(), $subscription->interval);
    }

    public function resume(Subscription $subscription): GatewaySubscriptionData
    {
        $remoteId = $subscription->gateway_id;

        if ($remoteId === null) {
            throw new BillingException('Cannot resume a subscription that has no Stripe id.');
        }

        $remote = $this->client()->subscriptions->update($remoteId, ['cancel_at_period_end' => false]);

        return $this->toSubscriptionData($remote->toArray(), $subscription->interval);
    }

    public function addPaymentMethod(Company $company, string $token): GatewayPaymentMethodData
    {
        $customerId = $this->createCustomer($company);

        $method = $this->client()->paymentMethods->attach($token, ['customer' => $customerId]);

        $this->client()->customers->update($customerId, [
            'invoice_settings' => ['default_payment_method' => $method->id],
        ]);

        return $this->toPaymentMethodData($method->toArray());
    }

    public function defaultPaymentMethod(Company $company): ?GatewayPaymentMethodData
    {
        $customerId = $this->customerId($company);

        if ($customerId === null) {
            return null;
        }

        $customer = $this->client()->customers->retrieve($customerId, [
            'expand' => ['invoice_settings.default_payment_method'],
        ])->toArray();

        $method = $customer['invoice_settings']['default_payment_method'] ?? null;

        return is_array($method) ? $this->toPaymentMethodData($method) : null;
    }

    /**
     * Reverse a charge through Stripe.
     *
     * The refund is keyed on the charge or payment-intent id the original
     * transaction recorded; without one there is nothing remote to reverse and
     * the caller is told so rather than being handed a silent success.
     *
     * A Stripe failure is returned as an unsuccessful DTO rather than thrown,
     * so the operator sees the processor's own reason on screen and the kit
     * still records the attempt.
     */
    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        $chargeId = $transaction->gateway_id;

        if (! is_string($chargeId) || $chargeId === '') {
            throw new BillingException('This transaction has no Stripe charge to refund.');
        }

        $payload = str_starts_with($chargeId, 'pi_')
            ? ['payment_intent' => $chargeId]
            : ['charge' => $chargeId];

        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        try {
            $refund = $this->client()->refunds->create($payload)->toArray();
        } catch (Throwable $exception) {
            return new GatewayRefundData(
                gateway: $this->name(),
                gatewayId: null,
                amount: $amount ?? $transaction->amount,
                currency: $transaction->currency,
                succeeded: false,
                failureReason: $exception->getMessage(),
            );
        }

        return new GatewayRefundData(
            gateway: $this->name(),
            gatewayId: self::stringOrNull($refund, 'id'),
            amount: (int) ($refund['amount'] ?? $amount ?? $transaction->amount),
            currency: strtoupper(self::stringOrNull($refund, 'currency') ?? $transaction->currency),
            succeeded: self::stringOrNull($refund, 'status') !== 'failed',
            processedAt: self::timestamp($refund, 'created'),
            failureReason: self::stringOrNull($refund, 'failure_reason'),
        );
    }

    /**
     * @return list<GatewayInvoiceData>
     */
    public function invoices(Company $company): array
    {
        $customerId = $this->customerId($company);

        if ($customerId === null) {
            return [];
        }

        $invoices = [];

        foreach ($this->client()->invoices->all(['customer' => $customerId, 'limit' => 100])->toArray()['data'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $invoices[] = new GatewayInvoiceData(
                gateway: $this->name(),
                gatewayId: self::stringOrNull($row, 'id'),
                number: self::stringOrNull($row, 'number') ?? (string) self::stringOrNull($row, 'id'),
                status: self::invoiceStatus(self::stringOrNull($row, 'status')),
                total: (int) ($row['total'] ?? 0),
                currency: strtoupper(self::stringOrNull($row, 'currency') ?? 'USD'),
                issuedAt: self::timestamp($row, 'created'),
                paidAt: self::timestamp($row, 'status_transitions.paid_at'),
                downloadUrl: self::stringOrNull($row, 'hosted_invoice_url'),
            );
        }

        return $invoices;
    }

    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        $secret = $this->webhookSecret();

        if ($secret === null) {
            return null;
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        $payload = $event->toArray();

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $event->id,
            type: $event->type,
            payload: $payload,
        );
    }

    /**
     * The console's stored secret key wins over Cashier's env configuration, so
     * an operator can configure Stripe from the gateways screen — while an
     * installation that predates that screen keeps working untouched.
     */
    protected function client(): StripeClient
    {
        $key = $this->configs->find($this->name())?->credential('secret_key');

        return $key === null ? Cashier::stripe() : new StripeClient($key);
    }

    /**
     * The signing secret, preferring the console's over Cashier's.
     */
    protected function webhookSecret(): ?string
    {
        $stored = $this->configs->find($this->name())?->credential('webhook_secret');

        if ($stored !== null) {
            return $stored;
        }

        $configured = config('cashier.webhook.secret');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    protected function customerId(Company $company): ?string
    {
        $stored = $this->settings->getFrom(
            SettingsRepository::SCOPE_COMPANY,
            $company->id,
            self::CUSTOMER_KEY,
        );

        return is_string($stored) && $stored !== '' ? $stored : null;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    protected function toSubscriptionData(array $remote, BillingInterval $interval): GatewaySubscriptionData
    {
        // Period boundaries moved onto subscription items in recent API
        // versions; read both shapes so an account on either behaves.
        $item = $remote['items']['data'][0] ?? [];
        $item = is_array($item) ? $item : [];

        return new GatewaySubscriptionData(
            gateway: $this->name(),
            gatewayId: self::stringOrNull($remote, 'id'),
            status: self::subscriptionStatus(self::stringOrNull($remote, 'status')),
            interval: $interval,
            trialEndsAt: self::timestamp($remote, 'trial_end'),
            currentPeriodStart: self::timestamp($remote, 'current_period_start') ?? self::timestamp($item, 'current_period_start'),
            currentPeriodEnd: self::timestamp($remote, 'current_period_end') ?? self::timestamp($item, 'current_period_end'),
            cancelsAt: self::timestamp($remote, 'cancel_at'),
            endedAt: self::timestamp($remote, 'ended_at'),
        );
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    protected function toPaymentMethodData(array $remote): GatewayPaymentMethodData
    {
        $card = $remote['card'] ?? [];
        $card = is_array($card) ? $card : [];

        $billing = $remote['billing_details'] ?? [];
        $billing = is_array($billing) ? $billing : [];

        return new GatewayPaymentMethodData(
            gateway: $this->name(),
            gatewayId: self::stringOrNull($remote, 'id') ?? '',
            type: self::stringOrNull($remote, 'type') ?? 'card',
            brand: self::stringOrNull($card, 'brand'),
            lastFour: self::stringOrNull($card, 'last4'),
            expMonth: isset($card['exp_month']) ? (int) $card['exp_month'] : null,
            expYear: isset($card['exp_year']) ? (int) $card['exp_year'] : null,
            holderName: self::stringOrNull($billing, 'name'),
        );
    }

    protected static function subscriptionStatus(?string $status): SubscriptionStatus
    {
        return match ($status) {
            'trialing' => SubscriptionStatus::Trialing,
            'active' => SubscriptionStatus::Active,
            'past_due', 'unpaid' => SubscriptionStatus::PastDue,
            'canceled' => SubscriptionStatus::Canceled,
            'incomplete', 'incomplete_expired' => SubscriptionStatus::Incomplete,
            default => SubscriptionStatus::Expired,
        };
    }

    protected static function invoiceStatus(?string $status): InvoiceStatus
    {
        return match ($status) {
            'paid' => InvoiceStatus::Paid,
            'open' => InvoiceStatus::Open,
            'void' => InvoiceStatus::Void,
            'uncollectible' => InvoiceStatus::Uncollectible,
            default => InvoiceStatus::Draft,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function stringOrNull(array $data, string $key): ?string
    {
        $value = data_get($data, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function timestamp(array $data, string $key): ?CarbonImmutable
    {
        $value = data_get($data, $key);

        if (! is_int($value) && ! is_numeric($value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromTimestampUTC((int) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
