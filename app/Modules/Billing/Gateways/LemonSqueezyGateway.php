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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;

/**
 * Lemon Squeezy — merchant of record for software sales.
 *
 * Like Paddle, they own the customer relationship and the tax, so the plan must
 * carry a Lemon Squeezy variant id in `gateway_prices` and refunds are theirs
 * to issue.
 */
class LemonSqueezyGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'lemonsqueezy';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $variantId = $plan->gatewayPrice($this->name(), $interval);

        if ($variantId === null) {
            throw new BillingException(__('The :plan plan has no Lemon Squeezy variant configured.', ['plan' => $plan->name]));
        }

        $response = $this->auth()->post('/checkouts', [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'checkout_data' => [
                        'email' => $company->owner->email,
                        'custom' => ['company' => $company->uuid, 'plan' => $plan->slug],
                    ],
                    'product_options' => ['redirect_url' => $returnUrl],
                    'test_mode' => $this->isTestMode(),
                ],
                'relationships' => [
                    'store' => ['data' => ['type' => 'stores', 'id' => $this->credential('store_id')]],
                    'variant' => ['data' => ['type' => 'variants', 'id' => $variantId]],
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new BillingException($this->errorFrom($response->json()) ?? __('Lemon Squeezy refused to open a checkout.'));
        }

        $id = $response->json('data.id');
        $url = $response->json('data.attributes.url');

        if (! is_string($id) || ! is_string($url)) {
            throw new BillingException(__('Lemon Squeezy returned an unusable checkout.'));
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $id);
    }

    /**
     * The redirect carries no order id, so the return leg cannot confirm
     * anything on its own — Lemon Squeezy's signed `order_created` webhook is
     * the authority, and returning null here keeps the customer on a "we are
     * confirming your payment" state rather than falsely reporting success.
     */
    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $orderId = $request->query('order_id');

        if (! is_string($orderId) || $orderId === '') {
            return null;
        }

        $response = $this->auth()->get("/orders/{$orderId}");

        if ($response->failed()) {
            return null;
        }

        $status = $response->json('data.attributes.status');

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $orderId,
            paid: $status === 'paid',
            amount: (int) $response->json('data.attributes.total', 0),
            currency: is_string($response->json('data.attributes.currency'))
                ? $response->json('data.attributes.currency')
                : 'USD',
            transactionId: $orderId,
            failureReason: is_string($status) && $status !== 'paid' ? $status : null,
        );
    }

    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        $signature = (string) $request->header('x-signature');
        $expected = hash_hmac('sha256', $request->getContent(), $this->credential('webhook_secret'));

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return null;
        }

        $event = $request->header('x-event-name');
        $id = $request->input('data.id');

        if (! is_string($event) || ! is_string($id)) {
            return null;
        }

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $id.'_'.$event,
            type: match ($event) {
                'order_created' => 'invoice.payment_succeeded',
                'order_refunded' => 'invoice.refunded',
                default => $event,
            },
            payload: ['data' => ['object' => ['id' => $id]]],
        );
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        return new GatewayRefundData(
            gateway: $this->name(),
            gatewayId: null,
            amount: $amount ?? $transaction->amount,
            currency: $transaction->currency,
            succeeded: false,
            failureReason: __('Lemon Squeezy is the merchant of record: refunds are issued from their dashboard.'),
        );
    }

    public function ping(): ?string
    {
        $response = $this->auth()->get('/users/me');

        return $response->successful() ? null : ($this->errorFrom($response->json()) ?? 'HTTP '.$response->status());
    }

    protected function baseUrl(): string
    {
        // One host; `test_mode` on the checkout selects the environment.
        return 'https://api.lemonsqueezy.com/v1';
    }

    protected function auth(): PendingRequest
    {
        return $this->http()
            ->withToken($this->credential('api_key'))
            ->withHeaders(['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json']);
    }

    protected function errorFrom(mixed $body): ?string
    {
        $detail = is_array($body) ? ($body['errors'][0]['detail'] ?? null) : null;

        return is_string($detail) ? $detail : null;
    }
}
