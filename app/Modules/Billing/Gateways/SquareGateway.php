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
use Illuminate\Support\Str;

/**
 * Square — hosted payment links.
 *
 * Webhooks are signed over the notification URL concatenated with the raw body,
 * which means the signature only validates against the exact URL Square was
 * configured with; that URL is derived from the route rather than stored, so a
 * moved installation fails loudly instead of silently accepting events.
 */
class SquareGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'square';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $reference = $this->name().'_'.Str::lower(Str::random(24));

        $response = $this->auth()->post('/v2/online-checkout/payment-links', [
            'idempotency_key' => $reference,
            'quick_pay' => [
                'name' => $plan->name.' — '.$interval->value,
                'price_money' => [
                    'amount' => $this->amountFor($plan, $interval),
                    'currency' => $this->currencyFor($plan),
                ],
                'location_id' => $this->credential('location_id'),
            ],
            'checkout_options' => ['redirect_url' => $returnUrl],
            'payment_note' => $company->uuid,
        ]);

        if ($response->failed()) {
            throw new BillingException($this->errorFrom($response->json()) ?? __('Square refused to open a checkout.'));
        }

        $orderId = $response->json('payment_link.order_id');
        $url = $response->json('payment_link.url');

        if (! is_string($orderId) || ! is_string($url)) {
            throw new BillingException(__('Square returned an unusable checkout.'));
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $orderId);
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $orderId = $request->query('orderId') ?? $request->query('reference');

        if (! is_string($orderId) || $orderId === '') {
            return null;
        }

        $response = $this->auth()->get("/v2/orders/{$orderId}");

        if ($response->failed()) {
            return null;
        }

        $state = $response->json('order.state');
        $tender = $response->json('order.tenders.0');

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $orderId,
            paid: $state === 'COMPLETED',
            amount: (int) $response->json('order.total_money.amount', 0),
            currency: is_string($response->json('order.total_money.currency'))
                ? $response->json('order.total_money.currency')
                : 'USD',
            // The payment id is what a refund is issued against.
            transactionId: is_array($tender) && is_string($tender['payment_id'] ?? null) ? $tender['payment_id'] : $orderId,
            failureReason: is_string($state) && $state !== 'COMPLETED' ? $state : null,
        );
    }

    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        $signature = (string) $request->header('x-square-hmacsha256-signature');
        $expected = base64_encode(hash_hmac(
            'sha256',
            $request->fullUrl().$request->getContent(),
            $this->credential('webhook_signature_key'),
            true,
        ));

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return null;
        }

        $type = $request->input('type');
        $id = $request->input('event_id');
        $orderId = $request->input('data.object.payment.order_id');

        if (! is_string($type) || ! is_string($id)) {
            return null;
        }

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $id,
            type: $type === 'payment.updated' ? 'invoice.payment_succeeded' : $type,
            payload: ['data' => ['object' => ['id' => is_string($orderId) ? $orderId : $id]]],
        );
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        $paymentId = $transaction->gateway_id;

        if (! is_string($paymentId) || $paymentId === '') {
            throw new BillingException(__('This transaction has no Square payment to refund.'));
        }

        $amount ??= $transaction->amount;

        $response = $this->auth()->post('/v2/refunds', [
            'idempotency_key' => 'refund_'.$transaction->id.'_'.$amount,
            'payment_id' => $paymentId,
            'amount_money' => ['amount' => $amount, 'currency' => $transaction->currency],
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
            gatewayId: is_string($response->json('refund.id')) ? $response->json('refund.id') : null,
            amount: $amount,
            currency: $transaction->currency,
            processedAt: CarbonImmutable::now(),
        );
    }

    public function ping(): ?string
    {
        $response = $this->auth()->get('/v2/locations');

        return $response->successful() ? null : ($this->errorFrom($response->json()) ?? 'HTTP '.$response->status());
    }

    protected function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://connect.squareupsandbox.com'
            : 'https://connect.squareup.com';
    }

    protected function auth(): PendingRequest
    {
        return $this->http()
            ->withToken($this->credential('access_token'))
            ->withHeaders(['Square-Version' => '2024-10-17']);
    }

    protected function errorFrom(mixed $body): ?string
    {
        $detail = is_array($body) ? ($body['errors'][0]['detail'] ?? null) : null;

        return is_string($detail) ? $detail : null;
    }
}
