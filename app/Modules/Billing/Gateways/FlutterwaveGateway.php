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
use Illuminate\Support\Str;

/**
 * Flutterwave — pan-African cards, mobile money and bank transfer.
 *
 * The redirect carries a `transaction_id` which is verified against the API;
 * webhooks are authenticated by a shared hash in the `verif-hash` header rather
 * than an HMAC, so the comparison is still constant-time but the secret is a
 * fixed string the operator sets in the dashboard.
 */
class FlutterwaveGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'flutterwave';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $reference = $this->name().'_'.Str::lower(Str::random(24));

        $response = $this->http()
            ->withToken($this->credential('secret_key'))
            ->post('/payments', [
                'tx_ref' => $reference,
                'amount' => $this->major($this->amountFor($plan, $interval)),
                'currency' => $this->currencyFor($plan),
                'redirect_url' => $returnUrl,
                'customer' => [
                    'email' => $company->owner->email,
                    'name' => $company->name,
                ],
                'customizations' => ['title' => $plan->name],
                'meta' => ['company' => $company->uuid, 'plan' => $plan->slug],
            ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new BillingException($this->errorFrom($response->json()) ?? __('Flutterwave refused to open a checkout.'));
        }

        $url = $response->json('data.link');

        if (! is_string($url)) {
            throw new BillingException(__('Flutterwave returned an unusable checkout.'));
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $reference);
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $id = $request->query('transaction_id');

        // `status=cancelled` comes back on the same URL, and is a definite "not
        // paid" rather than a verification failure.
        if ($request->query('status') === 'cancelled') {
            return new CheckoutResult(
                gateway: $this->name(),
                reference: (string) $request->query('tx_ref', ''),
                paid: false,
                amount: 0,
                currency: 'NGN',
                failureReason: 'cancelled',
            );
        }

        return is_string($id) && $id !== '' ? $this->fetch($id) : null;
    }

    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        $hash = (string) $request->header('verif-hash');

        if ($hash === '' || ! hash_equals($this->credential('webhook_hash'), $hash)) {
            return null;
        }

        $reference = $request->input('data.tx_ref') ?? $request->input('txRef');
        $status = $request->input('data.status') ?? $request->input('status');

        if (! is_string($reference)) {
            return null;
        }

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $reference.'_'.(is_string($status) ? $status : 'unknown'),
            type: $status === 'successful' ? 'invoice.payment_succeeded' : 'invoice.payment_failed',
            payload: ['data' => ['object' => ['id' => $reference, 'number' => $reference]]],
        );
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        $id = $transaction->gateway_id;

        if (! is_string($id) || $id === '') {
            throw new BillingException(__('This transaction has no Flutterwave id to refund.'));
        }

        $amount ??= $transaction->amount;

        $response = $this->http()
            ->withToken($this->credential('secret_key'))
            ->post("/transactions/{$id}/refund", ['amount' => $this->major($amount)]);

        if ($response->failed() || $response->json('status') !== 'success') {
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
            gatewayId: is_scalar($response->json('data.id')) ? (string) $response->json('data.id') : null,
            amount: $amount,
            currency: $transaction->currency,
            processedAt: CarbonImmutable::now(),
        );
    }

    public function ping(): ?string
    {
        $response = $this->http()->withToken($this->credential('secret_key'))->get('/banks/NG');

        return $response->successful() ? null : ($this->errorFrom($response->json()) ?? 'HTTP '.$response->status());
    }

    protected function baseUrl(): string
    {
        return 'https://api.flutterwave.com/v3';
    }

    protected function fetch(string $id): ?CheckoutResult
    {
        $response = $this->http()
            ->withToken($this->credential('secret_key'))
            ->get("/transactions/{$id}/verify");

        if ($response->failed() || $response->json('status') !== 'success') {
            return null;
        }

        $status = $response->json('data.status');
        $amount = $response->json('data.amount');

        return new CheckoutResult(
            gateway: $this->name(),
            reference: is_string($response->json('data.tx_ref')) ? $response->json('data.tx_ref') : $id,
            paid: $status === 'successful',
            amount: is_numeric($amount) ? (int) round(((float) $amount) * 100) : 0,
            currency: is_string($response->json('data.currency')) ? $response->json('data.currency') : 'NGN',
            transactionId: $id,
            failureReason: is_string($status) && $status !== 'successful' ? $status : null,
        );
    }

    protected function errorFrom(mixed $body): ?string
    {
        $message = is_array($body) ? ($body['message'] ?? null) : null;

        return is_string($message) ? $message : null;
    }
}
