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
 * Razorpay — cards, UPI and netbanking for India.
 *
 * Uses Payment Links rather than the JS modal, so the flow is a plain redirect
 * like every other driver here and needs no gateway-specific frontend. The
 * link's own id is the reference; webhooks are HMAC-SHA256 signed.
 */
class RazorpayGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'razorpay';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $response = $this->auth()->post('/payment_links', [
            'amount' => $this->amountFor($plan, $interval),
            'currency' => $this->currencyFor($plan),
            'description' => $plan->name.' — '.$interval->value,
            'customer' => [
                'name' => $company->name,
                'email' => $company->owner->email,
            ],
            'notify' => ['sms' => false, 'email' => false],
            'callback_url' => $returnUrl,
            'callback_method' => 'get',
            'notes' => ['company' => $company->uuid, 'plan' => $plan->slug],
        ]);

        if ($response->failed()) {
            throw new BillingException($this->errorFrom($response->json()) ?? __('Razorpay refused to open a checkout.'));
        }

        $id = $response->json('id');
        $url = $response->json('short_url');

        if (! is_string($id) || ! is_string($url)) {
            throw new BillingException(__('Razorpay returned an unusable checkout.'));
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $id);
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $id = $request->query('razorpay_payment_link_id') ?? $request->query('reference');

        return is_string($id) && $id !== '' ? $this->fetch($id) : null;
    }

    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        $signature = (string) $request->header('x-razorpay-signature');
        $expected = hash_hmac('sha256', $request->getContent(), $this->credential('webhook_secret'));

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return null;
        }

        $event = $request->input('event');
        $id = $request->input('payload.payment_link.entity.id')
            ?? $request->input('payload.payment.entity.id');

        if (! is_string($event) || ! is_string($id)) {
            return null;
        }

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $id.'_'.$event,
            type: str_contains($event, 'paid') || str_contains($event, 'captured')
                ? 'invoice.payment_succeeded'
                : 'invoice.payment_failed',
            payload: ['data' => ['object' => ['id' => $id, 'number' => $id]]],
        );
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        $paymentId = $transaction->gateway_id;

        if (! is_string($paymentId) || $paymentId === '') {
            throw new BillingException(__('This transaction has no Razorpay payment to refund.'));
        }

        $amount ??= $transaction->amount;

        $response = $this->auth()->post("/payments/{$paymentId}/refund", ['amount' => $amount]);

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
        $response = $this->auth()->get('/payments', ['count' => 1]);

        return $response->successful() ? null : ($this->errorFrom($response->json()) ?? 'HTTP '.$response->status());
    }

    protected function baseUrl(): string
    {
        // Razorpay has no separate test host: a key pair is either test or live.
        return 'https://api.razorpay.com/v1';
    }

    protected function fetch(string $id): ?CheckoutResult
    {
        $response = $this->auth()->get("/payment_links/{$id}");

        if ($response->failed()) {
            return null;
        }

        $status = $response->json('status');
        $payments = $response->json('payments');
        $paymentId = is_array($payments) && isset($payments[0]['payment_id']) && is_string($payments[0]['payment_id'])
            ? $payments[0]['payment_id']
            : $id;

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $id,
            paid: $status === 'paid',
            amount: (int) $response->json('amount_paid', 0),
            currency: is_string($response->json('currency')) ? $response->json('currency') : 'INR',
            // The refundable id is the payment, not the link.
            transactionId: $paymentId,
            failureReason: is_string($status) && $status !== 'paid' ? $status : null,
        );
    }

    /**
     * Razorpay authenticates with HTTP basic: key id as the user, secret as the
     * password.
     */
    protected function auth(): PendingRequest
    {
        return $this->http()->withBasicAuth($this->credential('key_id'), $this->credential('key_secret'));
    }

    protected function errorFrom(mixed $body): ?string
    {
        $description = is_array($body) ? ($body['error']['description'] ?? null) : null;

        return is_string($description) ? $description : null;
    }
}
