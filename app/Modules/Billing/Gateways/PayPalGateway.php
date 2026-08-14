<?php

declare(strict_types=1);

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\DTOs\CheckoutResult;
use App\Modules\Billing\DTOs\CheckoutSession;
use App\Modules\Billing\DTOs\GatewayRefundData;
use App\Modules\Billing\DTOs\GatewayWebhookData;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Company\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;

/**
 * PayPal — Orders v2.
 *
 * Two-stage by design: the order is created and approved by the payer, then
 * *captured* by us on the return leg. An approved-but-uncaptured order is not
 * money received, so `verifyCheckout()` captures before reporting a payment.
 */
class PayPalGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'paypal';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $response = $this->auth()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => $company->uuid,
                'description' => $plan->name.' — '.$interval->value,
                'amount' => [
                    'currency_code' => $this->currencyFor($plan),
                    'value' => $this->major($this->amountFor($plan, $interval)),
                ],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                        'user_action' => 'PAY_NOW',
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new BillingException($this->errorFrom($response->json()) ?? __('PayPal refused to open a checkout.'));
        }

        $id = $response->json('id');
        $url = $this->approvalUrl($response->json('links'));

        if (! is_string($id) || $url === null) {
            throw new BillingException(__('PayPal returned an unusable checkout.'));
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $id);
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $token = $request->query('token') ?? $request->query('reference');

        if (! is_string($token) || $token === '') {
            return null;
        }

        // Capture is the step that actually takes the money. It is idempotent
        // on PayPal's side for an already-captured order, which matters because
        // a customer can reload the return URL.
        $capture = $this->auth()->post("/v2/checkout/orders/{$token}/capture", []);

        if ($capture->failed() && $capture->status() !== 422) {
            return null;
        }

        $order = $capture->successful() ? $capture : $this->auth()->get("/v2/checkout/orders/{$token}");

        if ($order->failed()) {
            return null;
        }

        $status = $order->json('status');
        $unit = $order->json('purchase_units.0.payments.captures.0');
        $amount = is_array($unit) ? ($unit['amount']['value'] ?? null) : null;

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $token,
            paid: $status === 'COMPLETED',
            amount: is_string($amount) ? (int) round(((float) $amount) * 100) : 0,
            currency: is_array($unit) && is_string($unit['amount']['currency_code'] ?? null)
                ? $unit['amount']['currency_code']
                : 'USD',
            // The capture id, not the order id: a refund is issued against it.
            transactionId: is_array($unit) && is_string($unit['id'] ?? null) ? $unit['id'] : $token,
            failureReason: is_string($status) && $status !== 'COMPLETED' ? $status : null,
        );
    }

    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        $payload = $request->json()->all();

        $verification = $this->auth()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $request->header('paypal-auth-algo'),
            'cert_url' => $request->header('paypal-cert-url'),
            'transmission_id' => $request->header('paypal-transmission-id'),
            'transmission_sig' => $request->header('paypal-transmission-sig'),
            'transmission_time' => $request->header('paypal-transmission-time'),
            'webhook_id' => $this->credential('webhook_id'),
            'webhook_event' => $payload,
        ]);

        if ($verification->failed() || $verification->json('verification_status') !== 'SUCCESS') {
            return null;
        }

        $type = $request->input('event_type');
        $id = $request->input('id');

        if (! is_string($type) || ! is_string($id)) {
            return null;
        }

        $resource = $request->input('resource.supplementary_data.related_ids.order_id')
            ?? $request->input('resource.id');

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $id,
            type: $type === 'PAYMENT.CAPTURE.COMPLETED' ? 'invoice.payment_succeeded' : 'invoice.payment_failed',
            payload: ['data' => ['object' => ['id' => is_string($resource) ? $resource : $id]]],
        );
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        $captureId = $transaction->gateway_id;

        if (! is_string($captureId) || $captureId === '') {
            throw new BillingException(__('This transaction has no PayPal capture to refund.'));
        }

        $amount ??= $transaction->amount;

        $response = $this->auth()->post("/v2/payments/captures/{$captureId}/refund", [
            'amount' => ['value' => $this->major($amount), 'currency_code' => $transaction->currency],
        ]);

        if ($response->failed()) {
            return new GatewayRefundData(
                gateway: $this->name(),
                gatewayId: null,
                amount: $amount,
                currency: $transaction->currency,
                succeeded: false,
                failureReason: $this->errorFrom($response->json()),
            );
        }

        return new GatewayRefundData(
            gateway: $this->name(),
            gatewayId: is_string($response->json('id')) ? $response->json('id') : null,
            amount: $amount,
            currency: $transaction->currency,
            processedAt: CarbonImmutable::now(),
        );
    }

    public function ping(): ?string
    {
        return $this->token() === null ? 'Could not obtain an access token.' : null;
    }

    protected function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * PayPal issues a short-lived bearer token from the client credentials;
     * cached for the request so a checkout does not fetch it twice.
     */
    protected function token(): ?string
    {
        static $tokens = [];
        $key = $this->baseUrl();

        if (isset($tokens[$key])) {
            return $tokens[$key];
        }

        $response = $this->http()
            ->asForm()
            ->withBasicAuth($this->credential('client_id'), $this->credential('client_secret'))
            ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        $token = $response->successful() ? $response->json('access_token') : null;

        return $tokens[$key] = is_string($token) ? $token : null;
    }

    protected function auth(): PendingRequest
    {
        $token = $this->token();

        if ($token === null) {
            throw new BillingException(__('PayPal rejected these credentials.'));
        }

        return $this->http()->withToken($token);
    }

    protected function approvalUrl(mixed $links): ?string
    {
        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (is_array($link) && ($link['rel'] ?? null) === 'payer-action' && is_string($link['href'] ?? null)) {
                return $link['href'];
            }
        }

        foreach ($links as $link) {
            if (is_array($link) && ($link['rel'] ?? null) === 'approve' && is_string($link['href'] ?? null)) {
                return $link['href'];
            }
        }

        return null;
    }

    protected function errorFrom(mixed $body): ?string
    {
        $message = is_array($body) ? ($body['message'] ?? null) : null;

        return is_string($message) ? $message : null;
    }
}
