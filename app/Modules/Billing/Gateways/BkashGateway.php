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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * bKash Tokenized Checkout — the wallet directly, without an aggregator's cut.
 *
 * Authentication is two-legged: app credentials and a merchant username and
 * password are exchanged for a short-lived `id_token` which every other call
 * carries. The token is cached, because bKash rate-limits the grant endpoint
 * and a fresh grant per API call is the documented way to trip it.
 *
 * The customer returns by GET, so no callback bridge is involved. What does
 * need care is `execute`: it is the call that actually captures the money and
 * it is not idempotent. A customer who reloads the return page gets a refusal
 * on the second attempt, which must be read as "already captured" rather than
 * reported to them as a failed payment.
 */
class BkashGateway extends HostedCheckoutGateway
{
    /** Well short of the hour bKash grants, so a token is never used as it expires. */
    private const TOKEN_TTL_SECONDS = 3000;

    public function name(): string
    {
        return 'bkash';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $reference = 'INV'.Str::upper(Str::random(16));

        $response = $this->authenticated()->post('/tokenized/checkout/create', [
            // bKash's own code for "checkout, with a token".
            'mode' => '0011',
            'payerReference' => $company->uuid,
            'callbackURL' => $returnUrl,
            'merchantAssociationInfo' => 'MI05MID54RF09123456One',
            'amount' => $this->major($this->amountFor($plan, $interval)),
            'currency' => $this->currencyFor($plan),
            'intent' => 'sale',
            'merchantInvoiceNumber' => $reference,
        ]);

        $url = $response->json('bkashURL');
        $paymentId = $response->json('paymentID');

        if ($response->failed() || ! is_string($url) || ! is_string($paymentId)) {
            throw new BillingException(
                $this->errorFrom($response->json()) ?? __('bKash refused to open a checkout.'),
            );
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $paymentId);
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $paymentId = $request->query('paymentID');

        if (! is_string($paymentId) || $paymentId === '') {
            return null;
        }

        // A customer who backed out is told so directly; there is nothing to
        // execute and asking bKash would only produce a confusing error.
        $status = $request->query('status');

        if (is_string($status) && in_array(strtolower($status), ['cancel', 'failure'], true)) {
            return new CheckoutResult(
                gateway: $this->name(),
                reference: $paymentId,
                paid: false,
                amount: 0,
                currency: 'BDT',
                failureReason: strtolower($status),
            );
        }

        $payment = $this->execute($paymentId);

        if ($payment === null) {
            return null;
        }

        $paid = ($payment['transactionStatus'] ?? null) === 'Completed';
        $amount = (int) round(((float) ($payment['amount'] ?? 0)) * 100);
        $currency = is_string($payment['currency'] ?? null) ? strtoupper($payment['currency']) : 'BDT';

        if ($paid && ! $this->matchesExpectedCharge($request, $amount, $currency)) {
            return new CheckoutResult(
                gateway: $this->name(),
                reference: $paymentId,
                paid: false,
                amount: $amount,
                currency: $currency,
                failureReason: __('The amount bKash captured does not match this plan.'),
            );
        }

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $paymentId,
            paid: $paid,
            amount: $amount,
            currency: $currency,
            // trxID is the wallet transaction a refund is issued against.
            transactionId: is_string($payment['trxID'] ?? null) ? $payment['trxID'] : $paymentId,
            failureReason: $paid ? null : (is_string($payment['transactionStatus'] ?? null) ? $payment['transactionStatus'] : null),
        );
    }

    /**
     * bKash has no merchant webhook product; the return leg's execute call is
     * the only confirmation there is.
     */
    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        return null;
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        $trxId = $transaction->gateway_id;
        $paymentId = is_array($transaction->meta) ? ($transaction->meta['checkout_reference'] ?? null) : null;

        if (! is_string($trxId) || $trxId === '' || ! is_string($paymentId) || $paymentId === '') {
            throw new BillingException(__('This transaction has no bKash payment to refund.'));
        }

        $amount ??= $transaction->amount;

        $response = $this->authenticated()->post('/tokenized/checkout/payment/refund', [
            'paymentID' => $paymentId,
            'trxID' => $trxId,
            'amount' => $this->major($amount),
            'sku' => 'subscription',
            'reason' => __('Refunded from the operator console.'),
        ]);

        $status = $response->json('transactionStatus');

        if ($response->failed() || $status !== 'Completed') {
            return new GatewayRefundData(
                gateway: $this->name(),
                gatewayId: null,
                amount: $amount,
                currency: $transaction->currency,
                succeeded: false,
                failureReason: $this->errorFrom($response->json()) ?? (is_string($status) ? $status : 'HTTP '.$response->status()),
            );
        }

        return new GatewayRefundData(
            gateway: $this->name(),
            gatewayId: is_scalar($response->json('refundTrxID')) ? (string) $response->json('refundTrxID') : null,
            amount: $amount,
            currency: $transaction->currency,
            processedAt: CarbonImmutable::now(),
        );
    }

    public function ping(): ?string
    {
        // Granting a token exercises all four credentials at once, which is
        // exactly what the console's test button is for.
        try {
            $this->token(fresh: true);
        } catch (BillingException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    protected function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta';
    }

    /**
     * Capture the payment, tolerating the case where it already happened.
     *
     * `execute` is single-use. On a repeat — a reloaded return page, a
     * double-submitted callback — bKash refuses, and the payment status has to
     * be queried instead. Treating that refusal as a failed payment would tell
     * a customer who has been charged that they have not been.
     *
     * @return array<string, mixed>|null
     */
    protected function execute(string $paymentId): ?array
    {
        $response = $this->authenticated()->post('/tokenized/checkout/execute', ['paymentID' => $paymentId]);
        $body = $response->json();

        if ($response->successful() && is_array($body) && ($body['transactionStatus'] ?? null) === 'Completed') {
            return $body;
        }

        $status = $this->authenticated()->post('/tokenized/checkout/payment/status', ['paymentID' => $paymentId]);
        $statusBody = $status->json();

        if ($status->successful() && is_array($statusBody) && isset($statusBody['transactionStatus'])) {
            return $statusBody;
        }

        // Neither call produced an answer: unverifiable, which is not the same
        // as unpaid, and the caller must be able to tell them apart.
        return is_array($body) && isset($body['transactionStatus']) ? $body : null;
    }

    /**
     * A request carrying the id token and the app key, as every non-grant bKash
     * endpoint requires.
     */
    protected function authenticated(): PendingRequest
    {
        return $this->http()->withHeaders([
            'Authorization' => $this->token(),
            'X-APP-Key' => $this->credential('app_key'),
        ]);
    }

    /**
     * The cached id token, granted on demand.
     *
     * Keyed by mode as well as driver so switching an installation between
     * sandbox and live does not present a sandbox token to the live host.
     */
    protected function token(bool $fresh = false): string
    {
        $key = sprintf('billing:bkash:token:%s', $this->isTestMode() ? 'sandbox' : 'live');

        if ($fresh) {
            Cache::forget($key);
        }

        $token = Cache::remember($key, self::TOKEN_TTL_SECONDS, function (): ?string {
            $response = $this->http()
                ->withHeaders([
                    'username' => $this->credential('username'),
                    'password' => $this->credential('password'),
                ])
                ->post('/tokenized/checkout/token/grant', [
                    'app_key' => $this->credential('app_key'),
                    'app_secret' => $this->credential('app_secret'),
                ]);

            $token = $response->json('id_token');

            return is_string($token) && $token !== '' ? $token : null;
        });

        if (! is_string($token)) {
            // Never cache a failure: the next attempt must reach bKash again
            // rather than being told for the next fifty minutes that the
            // credentials are bad.
            Cache::forget($key);

            throw new BillingException(__('bKash rejected these credentials.'));
        }

        return $token;
    }

    protected function errorFrom(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $message = $body['errorMessage'] ?? $body['statusMessage'] ?? $body['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }
}
