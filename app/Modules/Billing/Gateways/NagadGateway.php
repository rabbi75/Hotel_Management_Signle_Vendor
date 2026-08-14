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
use App\Modules\Billing\Support\NagadCrypto;
use App\Modules\Company\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Nagad — the state-backed wallet, and the most involved integration here.
 *
 * Checkout is a two-step handshake rather than one call. `initialize` sends an
 * RSA-encrypted, RSA-signed payload and gets back a payment reference and a
 * challenge; `complete` echoes that challenge, also encrypted and signed, and
 * only then does Nagad return the URL to send the customer to. The envelope
 * lives in {@see NagadCrypto}.
 *
 * The customer comes back by GET, so no callback bridge is involved.
 *
 * Nagad publishes no merchant refund API — refunds go through their portal —
 * so `refund()` says so plainly rather than reporting a success that did not
 * happen, and the registry declares `supports_refunds: false` so the console
 * never offers the button.
 */
class NagadGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'nagad';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $orderId = Str::upper(Str::random(20));
        $merchantId = $this->credential('merchant_id');
        $now = CarbonImmutable::now('Asia/Dhaka');
        $crypto = $this->crypto();

        $sensitive = [
            'merchantId' => $merchantId,
            'datetime' => $now->format('YmdHis'),
            'orderId' => $orderId,
            'challenge' => Str::random(40),
        ];

        $initialise = $this->http()
            ->withHeaders($this->headers())
            ->post("/check-out/initialize/{$merchantId}/{$orderId}", [
                'accountNumber' => $this->credential('merchant_number'),
                'dateTime' => $now->format('YmdHis'),
                'sensitiveData' => $crypto->encrypt($sensitive),
                'signature' => $crypto->sign($sensitive),
            ]);

        $decoded = $this->decoded($initialise->json('sensitiveData'), $crypto);

        if ($initialise->failed() || $decoded === null) {
            throw new BillingException(
                $this->errorFrom($initialise->json()) ?? __('Nagad refused to start a payment.'),
            );
        }

        $paymentReferenceId = $decoded['paymentReferenceId'] ?? null;
        $challenge = $decoded['challenge'] ?? null;

        if (! is_string($paymentReferenceId) || ! is_string($challenge)) {
            throw new BillingException(__('Nagad returned an unusable payment reference.'));
        }

        $order = [
            'merchantId' => $merchantId,
            'orderId' => $orderId,
            'currencyCode' => '050', // ISO 4217 numeric for BDT; Nagad takes no other.
            'amount' => $this->major($this->amountFor($plan, $interval)),
            'challenge' => $challenge,
        ];

        $complete = $this->http()
            ->withHeaders($this->headers())
            ->post("/check-out/complete/{$paymentReferenceId}", [
                'sensitiveData' => $crypto->encrypt($order),
                'signature' => $crypto->sign($order),
                'merchantCallbackURL' => $returnUrl,
                'additionalMerchantInfo' => (object) [
                    'company' => $company->uuid,
                    'plan' => $plan->slug,
                ],
            ]);

        $url = $complete->json('callBackUrl');

        if ($complete->failed() || ! is_string($url) || $url === '') {
            throw new BillingException(
                $this->errorFrom($complete->json()) ?? __('Nagad refused to open a checkout.'),
            );
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $paymentReferenceId);
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $reference = $request->query('payment_ref_id');

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $response = $this->http()
            ->withHeaders($this->headers())
            ->get('/verify/payment/'.urlencode($reference));

        if ($response->failed()) {
            return null;
        }

        $status = $response->json('status');

        if (! is_string($status)) {
            return null;
        }

        $paid = $status === 'Success';
        $amount = (int) round(((float) $response->json('amount', 0)) * 100);
        $currency = 'BDT';

        if ($paid && ! $this->matchesExpectedCharge($request, $amount, $currency)) {
            return new CheckoutResult(
                gateway: $this->name(),
                reference: $reference,
                paid: false,
                amount: $amount,
                currency: $currency,
                failureReason: __('The amount Nagad settled does not match this plan.'),
            );
        }

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $reference,
            paid: $paid,
            amount: $amount,
            currency: $currency,
            transactionId: is_string($response->json('issuerPaymentRefNo')) ? $response->json('issuerPaymentRefNo') : $reference,
            failureReason: $paid ? null : $status,
        );
    }

    /**
     * Nagad has no merchant webhook product.
     */
    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        return null;
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        throw new BillingException(
            __('Nagad refunds are raised in the Nagad merchant portal; this one cannot be issued from here.'),
        );
    }

    public function ping(): ?string
    {
        // Nagad offers no credential-only endpoint, so the check is that the key
        // pair itself is usable — which is the misconfiguration that actually
        // happens, a PEM pasted with a line missing or the two keys swapped.
        try {
            $crypto = $this->crypto();
            $probe = ['merchantId' => $this->credential('merchant_id'), 'datetime' => CarbonImmutable::now()->format('YmdHis')];

            $crypto->encrypt($probe);
            $crypto->sign($probe);
        } catch (BillingException $exception) {
            return $exception->getMessage();
        }

        $response = $this->http()->withHeaders($this->headers())->get('/verify/payment/ping');

        // Any answer at all means the host is reachable and the merchant
        // headers were accepted; an unknown reference is the expected reply.
        return $response->serverError() ? 'HTTP '.$response->status() : null;
    }

    protected function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs'
            : 'https://api.mynagad.com/api/dfs';
    }

    protected function crypto(): NagadCrypto
    {
        return new NagadCrypto(
            nagadPublicKey: $this->credential('public_key'),
            merchantPrivateKey: $this->credential('private_key'),
        );
    }

    /**
     * Nagad requires the caller's IP and a client identifier on every call and
     * rejects the request outright without them.
     *
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [
            'X-KM-Api-Version' => 'v-0.2.0',
            'X-KM-IP-V4' => (string) (request()->server('SERVER_ADDR') ?? '127.0.0.1'),
            'X-KM-Client-Type' => 'PC_WEB',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decoded(mixed $sensitiveData, NagadCrypto $crypto): ?array
    {
        return is_string($sensitiveData) && $sensitiveData !== ''
            ? $crypto->decrypt($sensitiveData)
            : null;
    }

    protected function errorFrom(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $message = $body['message'] ?? $body['reason'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }
}
