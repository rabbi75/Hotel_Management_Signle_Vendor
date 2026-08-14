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
 * Paystack — cards, bank transfer and mobile money across Africa.
 *
 * Initialise returns an authorisation URL and a reference; the reference is
 * then verified server-side. Webhooks are signed with HMAC-SHA512 over the raw
 * body using the secret key.
 */
class PaystackGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'paystack';
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
            ->post('/transaction/initialize', [
                'email' => $company->owner->email,
                'amount' => $this->amountFor($plan, $interval),
                'currency' => $this->currencyFor($plan),
                'reference' => $reference,
                'callback_url' => $returnUrl,
                'metadata' => ['company' => $company->uuid, 'plan' => $plan->slug],
            ]);

        if ($response->failed() || $response->json('status') !== true) {
            throw new BillingException($this->errorFrom($response->json()) ?? __('Paystack refused to open a checkout.'));
        }

        $url = $response->json('data.authorization_url');

        if (! is_string($url)) {
            throw new BillingException(__('Paystack returned an unusable checkout.'));
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $reference);
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        // Paystack appends `reference` itself; the kit's own copy is the
        // fallback for a customer who lost the query string.
        $reference = $request->query('reference') ?? $request->query('trxref');

        return is_string($reference) && $reference !== '' ? $this->fetch($reference) : null;
    }

    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        $signature = (string) $request->header('x-paystack-signature');
        $expected = hash_hmac('sha512', $request->getContent(), $this->credential('secret_key'));

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return null;
        }

        $event = $request->input('event');
        $reference = $request->input('data.reference');

        if (! is_string($event) || ! is_string($reference)) {
            return null;
        }

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $reference.'_'.$event,
            type: $event === 'charge.success' ? 'invoice.payment_succeeded' : 'invoice.payment_failed',
            payload: ['data' => ['object' => ['id' => $reference, 'number' => $reference]]],
        );
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        $reference = $transaction->gateway_id;

        if (! is_string($reference) || $reference === '') {
            throw new BillingException(__('This transaction has no Paystack reference to refund.'));
        }

        $amount ??= $transaction->amount;

        $response = $this->http()
            ->withToken($this->credential('secret_key'))
            ->post('/refund', ['transaction' => $reference, 'amount' => $amount]);

        if ($response->failed() || $response->json('status') !== true) {
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
        $response = $this->http()->withToken($this->credential('secret_key'))->get('/bank');

        return $response->successful() ? null : ($this->errorFrom($response->json()) ?? 'HTTP '.$response->status());
    }

    protected function baseUrl(): string
    {
        // One host; the key prefix (`sk_test_` / `sk_live_`) selects the mode.
        return 'https://api.paystack.co';
    }

    protected function fetch(string $reference): ?CheckoutResult
    {
        $response = $this->http()
            ->withToken($this->credential('secret_key'))
            ->get('/transaction/verify/'.urlencode($reference));

        if ($response->failed() || $response->json('status') !== true) {
            return null;
        }

        $status = $response->json('data.status');

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $reference,
            paid: $status === 'success',
            amount: (int) $response->json('data.amount', 0),
            currency: is_string($response->json('data.currency')) ? $response->json('data.currency') : 'NGN',
            transactionId: $reference,
            failureReason: is_string($status) && $status !== 'success' ? $status : null,
        );
    }

    protected function errorFrom(mixed $body): ?string
    {
        $message = is_array($body) ? ($body['message'] ?? null) : null;

        return is_string($message) ? $message : null;
    }
}
