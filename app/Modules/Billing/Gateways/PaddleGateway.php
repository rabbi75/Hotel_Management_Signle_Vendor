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
 * Paddle Billing — merchant of record.
 *
 * Paddle sells to the customer on your behalf and handles sales tax, so a
 * "refund" is a credit request they adjudicate rather than an instruction we
 * can issue. That is reflected honestly below: `refund()` reports failure with
 * an explanation instead of pretending the money moved.
 *
 * Requires the plan's `gateway_prices` to carry a Paddle price id — Paddle
 * prices the product, so the amount is not ours to send.
 */
class PaddleGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'paddle';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $priceId = $plan->gatewayPrice($this->name(), $interval);

        if ($priceId === null) {
            throw new BillingException(__('The :plan plan has no Paddle price configured.', ['plan' => $plan->name]));
        }

        $response = $this->auth()->post('/transactions', [
            'items' => [['price_id' => $priceId, 'quantity' => 1]],
            'custom_data' => ['company' => $company->uuid, 'plan' => $plan->slug],
            'checkout' => ['url' => $returnUrl],
        ]);

        if ($response->failed()) {
            throw new BillingException($this->errorFrom($response->json()) ?? __('Paddle refused to open a checkout.'));
        }

        $id = $response->json('data.id');
        $url = $response->json('data.checkout.url');

        if (! is_string($id) || ! is_string($url)) {
            throw new BillingException(__('Paddle returned an unusable checkout.'));
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $id);
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $id = $request->query('_ptxn') ?? $request->query('reference');

        if (! is_string($id) || $id === '') {
            return null;
        }

        $response = $this->auth()->get("/transactions/{$id}");

        if ($response->failed()) {
            return null;
        }

        $status = $response->json('data.status');
        $total = $response->json('data.details.totals.grand_total');

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $id,
            paid: $status === 'completed' || $status === 'paid',
            amount: is_numeric($total) ? (int) $total : 0,
            currency: is_string($response->json('data.currency_code')) ? $response->json('data.currency_code') : 'USD',
            transactionId: $id,
            failureReason: is_string($status) && $status !== 'completed' ? $status : null,
        );
    }

    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        if (! $this->signatureIsValid($request)) {
            return null;
        }

        $type = $request->input('event_type');
        $id = $request->input('event_id');
        $transactionId = $request->input('data.id');

        if (! is_string($type) || ! is_string($id)) {
            return null;
        }

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $id,
            type: match ($type) {
                'transaction.completed', 'transaction.paid' => 'invoice.payment_succeeded',
                'transaction.payment_failed' => 'invoice.payment_failed',
                default => $type,
            },
            payload: ['data' => ['object' => ['id' => is_string($transactionId) ? $transactionId : $id]]],
        );
    }

    /**
     * Paddle owns the merchant relationship, so refunds are requested through
     * their dashboard and approved by them. Reporting failure with the reason
     * is more useful than a silent no-op that leaves the ledger wrong.
     */
    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        return new GatewayRefundData(
            gateway: $this->name(),
            gatewayId: null,
            amount: $amount ?? $transaction->amount,
            currency: $transaction->currency,
            succeeded: false,
            failureReason: __('Paddle is the merchant of record: refunds are requested from the Paddle dashboard.'),
        );
    }

    public function ping(): ?string
    {
        $response = $this->auth()->get('/event-types');

        return $response->successful() ? null : ($this->errorFrom($response->json()) ?? 'HTTP '.$response->status());
    }

    protected function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';
    }

    protected function auth(): PendingRequest
    {
        return $this->http()->withToken($this->credential('api_key'));
    }

    /**
     * `ts=…;h1=…` over `ts:body`, HMAC-SHA256.
     */
    protected function signatureIsValid(Request $request): bool
    {
        $header = (string) $request->header('paddle-signature');

        if ($header === '') {
            return false;
        }

        $parts = [];

        foreach (explode(';', $header) as $segment) {
            [$key, $value] = array_pad(explode('=', $segment, 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = $parts['ts'] ?? '';
        $signature = $parts['h1'] ?? '';

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.':'.$request->getContent(), $this->credential('webhook_secret'));

        return hash_equals($expected, $signature);
    }

    protected function errorFrom(mixed $body): ?string
    {
        $detail = is_array($body) ? ($body['error']['detail'] ?? null) : null;

        return is_string($detail) ? $detail : null;
    }
}
