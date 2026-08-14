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
use Illuminate\Support\Facades\URL;

/**
 * Braintree — cards and PayPal, over the GraphQL API.
 *
 * Braintree has no hosted checkout page of its own: the drop-in UI is rendered
 * in the merchant's own page against a client token. So `checkout()` points at
 * a route this application serves, which collects the nonce and posts it back —
 * the redirect shape stays identical to every other driver, and the difference
 * is confined to which page the customer lands on.
 *
 * That page loads Braintree's script from their CDN. The application sets no
 * Content-Security-Policy of its own (see SecureHeaders), but a deployment that
 * adds one at the web server must allow `js.braintreegateway.com` or this
 * driver's checkout will render blank.
 */
class BraintreeGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'braintree';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $token = $this->clientToken();

        if ($token === null) {
            throw new BillingException(__('Braintree rejected these credentials.'));
        }

        // The drop-in is hosted here; the client token and the amount travel in
        // the signed URL so the page needs no session of its own.
        $url = URL::temporarySignedRoute('billing.checkout.braintree', now()->addMinutes(30), [
            'plan' => $plan->slug,
            'interval' => $interval->value,
            'return' => $returnUrl,
            'cancel' => $cancelUrl,
        ]);

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $token);
    }

    /**
     * The drop-in posts a payment nonce back; that nonce is what gets charged.
     */
    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $nonce = $request->input('payment_method_nonce') ?? $request->query('nonce');
        $amount = $request->input('amount') ?? $request->query('amount');
        $currency = (string) ($request->input('currency') ?? $request->query('currency', 'USD'));

        if (! is_string($nonce) || $nonce === '' || ! is_numeric($amount)) {
            return null;
        }

        $response = $this->graphql(
            'mutation ($input: ChargePaymentMethodInput!) {
                chargePaymentMethod(input: $input) {
                    transaction { id status amount { value currencyCode } }
                }
            }',
            ['input' => [
                'paymentMethodId' => $nonce,
                'transaction' => ['amount' => $this->major((int) $amount)],
            ]],
        );

        if ($response === null) {
            return null;
        }

        $transaction = $response['data']['chargePaymentMethod']['transaction'] ?? null;

        if (! is_array($transaction)) {
            return null;
        }

        $status = $transaction['status'] ?? null;
        $value = $transaction['amount']['value'] ?? null;

        return new CheckoutResult(
            gateway: $this->name(),
            reference: is_string($transaction['id'] ?? null) ? $transaction['id'] : $nonce,
            paid: in_array($status, ['SUBMITTED_FOR_SETTLEMENT', 'SETTLING', 'SETTLED'], true),
            amount: is_numeric($value) ? (int) round(((float) $value) * 100) : (int) $amount,
            currency: is_string($transaction['amount']['currencyCode'] ?? null)
                ? $transaction['amount']['currencyCode']
                : strtoupper($currency),
            transactionId: is_string($transaction['id'] ?? null) ? $transaction['id'] : null,
            failureReason: is_string($status) ? $status : null,
        );
    }

    /**
     * Braintree signs its webhooks with `signature|payload`, where the payload
     * is base64 and the signature is keyed on the private key.
     */
    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        $signature = (string) $request->input('bt_signature');
        $payload = (string) $request->input('bt_payload');

        if ($signature === '' || $payload === '') {
            return null;
        }

        $matched = false;

        foreach (explode('&', $signature) as $pair) {
            [$publicKey, $candidate] = array_pad(explode('|', $pair, 2), 2, '');

            if ($publicKey !== $this->credential('public_key')) {
                continue;
            }

            $expected = hash_hmac('sha1', $payload, sha1($this->credential('private_key'), true));
            $matched = hash_equals($expected, $candidate);

            break;
        }

        if (! $matched) {
            return null;
        }

        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            return null;
        }

        // The notification body is XML; only the kind and the id are needed to
        // route it, so it is not worth a parser.
        preg_match('/<kind>([^<]+)<\/kind>/', $decoded, $kind);
        preg_match('/<id>([^<]+)<\/id>/', $decoded, $id);

        if (! isset($kind[1], $id[1])) {
            return null;
        }

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $id[1].'_'.$kind[1],
            type: match ($kind[1]) {
                'transaction_settled' => 'invoice.payment_succeeded',
                'transaction_settlement_declined' => 'invoice.payment_failed',
                default => $kind[1],
            },
            payload: ['data' => ['object' => ['id' => $id[1]]]],
        );
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        $remoteId = $transaction->gateway_id;

        if (! is_string($remoteId) || $remoteId === '') {
            throw new BillingException(__('This transaction has no Braintree transaction to refund.'));
        }

        $amount ??= $transaction->amount;

        $response = $this->graphql(
            'mutation ($input: RefundTransactionInput!) {
                refundTransaction(input: $input) { refund { id status } }
            }',
            ['input' => [
                'transactionId' => $remoteId,
                'refund' => ['amount' => $this->major($amount)],
            ]],
        );

        $refund = $response['data']['refundTransaction']['refund'] ?? null;

        if (! is_array($refund)) {
            return new GatewayRefundData(
                gateway: $this->name(),
                gatewayId: null,
                amount: $amount,
                currency: $transaction->currency,
                succeeded: false,
                failureReason: is_array($response) && isset($response['errors'][0]['message'])
                    && is_string($response['errors'][0]['message'])
                        ? $response['errors'][0]['message']
                        : null,
            );
        }

        return new GatewayRefundData(
            gateway: $this->name(),
            gatewayId: is_string($refund['id'] ?? null) ? $refund['id'] : null,
            amount: $amount,
            currency: $transaction->currency,
            processedAt: CarbonImmutable::now(),
        );
    }

    public function ping(): ?string
    {
        return $this->clientToken() === null ? 'Could not obtain a client token.' : null;
    }

    protected function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://payments.sandbox.braintree-api.com'
            : 'https://payments.braintree-api.com';
    }

    protected function auth(): PendingRequest
    {
        return $this->http()
            ->withBasicAuth($this->credential('public_key'), $this->credential('private_key'))
            ->withHeaders(['Braintree-Version' => '2019-01-01']);
    }

    protected function clientToken(): ?string
    {
        $response = $this->graphql('mutation { createClientToken(input: {}) { clientToken } }');

        $token = $response['data']['createClientToken']['clientToken'] ?? null;

        return is_string($token) ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>|null
     */
    protected function graphql(string $query, array $variables = []): ?array
    {
        $response = $this->auth()->post('/graphql', array_filter([
            'query' => $query,
            'variables' => $variables === [] ? null : $variables,
        ]));

        if ($response->failed()) {
            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }
}
