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
use Illuminate\Http\Request;

/**
 * Mollie — iDEAL, Bancontact, SEPA and cards for Europe.
 *
 * Mollie's own webhook carries only a payment id and no signature, which is by
 * design: the id is meaningless without the API key, and the integration is
 * expected to fetch the payment rather than trust the body. Both the webhook
 * and the return leg therefore go through the same `fetch()`.
 */
class MollieGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'mollie';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $response = $this->http()
            ->withToken($this->credential('api_key'))
            ->post('/payments', [
                'amount' => [
                    'currency' => $this->currencyFor($plan),
                    'value' => $this->major($this->amountFor($plan, $interval)),
                ],
                'description' => $plan->name.' — '.$interval->value,
                'redirectUrl' => $returnUrl,
                'cancelUrl' => $cancelUrl,
                'metadata' => ['company' => $company->uuid, 'plan' => $plan->slug],
            ]);

        if ($response->failed()) {
            throw new BillingException($this->errorFrom($response->json()) ?? __('Mollie refused to open a checkout.'));
        }

        $id = $response->json('id');
        $url = $response->json('_links.checkout.href');

        if (! is_string($id) || ! is_string($url)) {
            throw new BillingException(__('Mollie returned an unusable checkout.'));
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $id);
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        // Mollie appends nothing to the redirect URL, so the reference is the
        // one the kit put there when it built the return link.
        $id = $request->query('reference');

        return is_string($id) && $id !== '' ? $this->fetch($id) : null;
    }

    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        $id = $request->input('id');

        if (! is_string($id) || $id === '') {
            return null;
        }

        $result = $this->fetch($id);

        if (! $result instanceof CheckoutResult) {
            return null;
        }

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $id.'_'.($result->paid ? 'paid' : 'failed'),
            type: $result->paid ? 'invoice.payment_succeeded' : 'invoice.payment_failed',
            payload: ['data' => ['object' => ['id' => $id, 'number' => $result->reference]]],
        );
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        $paymentId = $transaction->gateway_id;

        if (! is_string($paymentId) || $paymentId === '') {
            throw new BillingException(__('This transaction has no Mollie payment to refund.'));
        }

        $amount ??= $transaction->amount;

        $response = $this->http()
            ->withToken($this->credential('api_key'))
            ->post("/payments/{$paymentId}/refunds", [
                'amount' => ['currency' => $transaction->currency, 'value' => $this->major($amount)],
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
        $response = $this->http()->withToken($this->credential('api_key'))->get('/methods');

        return $response->successful() ? null : ($this->errorFrom($response->json()) ?? 'HTTP '.$response->status());
    }

    protected function baseUrl(): string
    {
        // One host for both modes; the key prefix (`test_` / `live_`) is what
        // selects the environment.
        return 'https://api.mollie.com/v2';
    }

    /**
     * The single place a Mollie payment is turned into a verified outcome.
     */
    protected function fetch(string $id): ?CheckoutResult
    {
        $response = $this->http()->withToken($this->credential('api_key'))->get("/payments/{$id}");

        if ($response->failed()) {
            return null;
        }

        $status = $response->json('status');
        $value = $response->json('amount.value');

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $id,
            paid: $status === 'paid',
            amount: is_string($value) ? (int) round(((float) $value) * 100) : 0,
            currency: is_string($response->json('amount.currency')) ? $response->json('amount.currency') : 'EUR',
            transactionId: $id,
            failureReason: is_string($status) && $status !== 'paid' ? $status : null,
        );
    }

    protected function errorFrom(mixed $body): ?string
    {
        $detail = is_array($body) ? ($body['detail'] ?? null) : null;

        return is_string($detail) ? $detail : null;
    }
}
