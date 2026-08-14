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
 * SSLCommerz — the aggregator most Bangladeshi merchants sell through.
 *
 * One integration reaches bKash, Nagad, Rocket, Upay, cards and net banking, so
 * this is the driver to enable first for BDT. The customer is sent to a hosted
 * page and comes back by *form POST*, which is why the success URL points at
 * the stateless callback bridge rather than straight at the return route.
 *
 * Two independent confirmations exist and both are used: the validation API,
 * called on the return leg, and the IPN, which is signed and arrives even if
 * the customer closes the tab before being redirected.
 */
class SslCommerzGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'sslcommerz';
    }

    public function checkout(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $reference = $this->name().'_'.Str::lower(Str::random(24));
        $owner = $company->owner;

        $response = $this->http()->post('/gwprocess/v4/api.php', [
            'store_id' => $this->credential('store_id'),
            'store_passwd' => $this->credential('store_password'),

            // SSLCommerz prices in major units, unlike most of the processors here.
            'total_amount' => $this->major($this->amountFor($plan, $interval)),
            'currency' => $this->currencyFor($plan),
            'tran_id' => $reference,

            // The success and failure legs are POSTed to, so they go through the
            // bridge; cancel is a plain redirect and does not need it.
            'success_url' => $this->bridgeUrl($returnUrl),
            'fail_url' => $this->bridgeUrl($returnUrl),
            'cancel_url' => $cancelUrl,
            'ipn_url' => route('api.billing.webhook', ['gateway' => $this->name()]),

            'product_name' => $plan->name,
            'product_category' => 'subscription',
            'product_profile' => 'general',

            // Required by SSLCommerz even for a digital sale; the placeholders
            // are what their own integration guide uses for one.
            'cus_name' => $owner->name,
            'cus_email' => $owner->email,
            'cus_phone' => $owner->phone ?? 'N/A',
            'cus_add1' => 'N/A',
            'cus_city' => 'N/A',
            'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO',

            'value_a' => $company->uuid,
            'value_b' => $plan->slug,
        ]);

        if ($response->failed() || $response->json('status') !== 'SUCCESS') {
            throw new BillingException(
                $this->errorFrom($response->json()) ?? __('SSLCommerz refused to open a checkout.'),
            );
        }

        $url = $response->json('GatewayPageURL');

        if (! is_string($url) || $url === '') {
            throw new BillingException(__('SSLCommerz returned an unusable checkout.'));
        }

        return new CheckoutSession(gateway: $this->name(), url: $url, reference: $reference);
    }

    /**
     * The bridge hands back `val_id` — the handle the validation API takes —
     * rather than our own `tran_id`, because only the former can be verified.
     */
    public function referenceFromCallback(Request $request): ?string
    {
        $valId = $request->input('val_id');

        return is_string($valId) && $valId !== '' ? $valId : null;
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $valId = $request->query('ref');

        if (! is_string($valId) || $valId === '') {
            return null;
        }

        $response = $this->http()->get('/validator/api/validationserverAPI.php', [
            'val_id' => $valId,
            'store_id' => $this->credential('store_id'),
            'store_passwd' => $this->credential('store_password'),
            'format' => 'json',
        ]);

        if ($response->failed()) {
            return null;
        }

        $status = $response->json('status');

        if (! is_string($status)) {
            return null;
        }

        // VALID is a live settlement; VALIDATED is one already validated by an
        // earlier call — a customer who reloads the return page, typically.
        $paid = in_array($status, ['VALID', 'VALIDATED'], true);
        $amount = (int) round(((float) $response->json('amount', 0)) * 100);
        $currency = is_string($response->json('currency')) ? strtoupper($response->json('currency')) : 'BDT';
        $reference = is_string($response->json('tran_id')) ? $response->json('tran_id') : $valId;

        // A reference the customer's browser has seen is a reference they can
        // substitute. Confirm the money actually captured is the money this
        // plan costs before treating it as paid.
        if ($paid && ! $this->matchesExpectedCharge($request, $amount, $currency)) {
            return new CheckoutResult(
                gateway: $this->name(),
                reference: $reference,
                paid: false,
                amount: $amount,
                currency: $currency,
                failureReason: __('The amount SSLCommerz settled does not match this plan.'),
            );
        }

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $reference,
            paid: $paid,
            amount: $amount,
            currency: $currency,
            // The bank transaction id is what a refund is issued against, not
            // our own reference.
            transactionId: is_string($response->json('bank_tran_id')) ? $response->json('bank_tran_id') : $reference,
            failureReason: $paid ? null : $status,
        );
    }

    /**
     * The IPN, which carries its own hash rather than a header signature.
     */
    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        if (! $this->verifiesIpn($request)) {
            return null;
        }

        $reference = $request->input('tran_id');
        $status = $request->input('status');

        if (! is_string($reference) || ! is_string($status)) {
            return null;
        }

        return new GatewayWebhookData(
            gateway: $this->name(),
            eventId: $reference.'_'.$status,
            type: $status === 'VALID' ? 'invoice.payment_succeeded' : 'invoice.payment_failed',
            payload: ['data' => ['object' => ['id' => $reference, 'number' => $reference]]],
        );
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        $bankTransactionId = $transaction->gateway_id;

        if (! is_string($bankTransactionId) || $bankTransactionId === '') {
            throw new BillingException(__('This transaction has no SSLCommerz bank reference to refund.'));
        }

        $amount ??= $transaction->amount;

        $response = $this->http()->get('/validator/api/merchantTransIDvalidationAPI.php', [
            'bank_tran_id' => $bankTransactionId,
            'refund_amount' => $this->major($amount),
            'refund_remarks' => __('Refunded from the operator console.'),
            'store_id' => $this->credential('store_id'),
            'store_passwd' => $this->credential('store_password'),
            'format' => 'json',
        ]);

        $status = $response->json('status');
        $succeeded = $response->successful() && in_array($status, ['success', 'SUCCESS', 'processing'], true);

        if (! $succeeded) {
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
            gatewayId: is_scalar($response->json('refund_ref_id')) ? (string) $response->json('refund_ref_id') : null,
            amount: $amount,
            currency: $transaction->currency,
            processedAt: CarbonImmutable::now(),
        );
    }

    public function ping(): ?string
    {
        // Validating a reference that cannot exist is the cheapest authenticated
        // call SSLCommerz offers: bad credentials are refused outright, while
        // good ones simply report the transaction as not found.
        $response = $this->http()->get('/validator/api/validationserverAPI.php', [
            'val_id' => 'ping',
            'store_id' => $this->credential('store_id'),
            'store_passwd' => $this->credential('store_password'),
            'format' => 'json',
        ]);

        if ($response->failed()) {
            return 'HTTP '.$response->status();
        }

        $status = $response->json('status');

        // INVALID_TRANSACTION means the store was recognised; anything naming
        // the credentials means it was not.
        return in_array($status, ['INVALID_TRANSACTION', 'VALID', 'VALIDATED'], true)
            ? null
            : ($this->errorFrom($response->json()) ?? (is_string($status) ? $status : 'Unexpected response.'));
    }

    protected function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    /**
     * SSLCommerz takes form-encoded bodies, not the JSON the base class sends.
     */
    protected function http(): PendingRequest
    {
        return parent::http()->asForm();
    }

    /**
     * The success URL handed to SSLCommerz: the stateless bridge, carrying the
     * real signed return URL. See CheckoutCallbackController for why the
     * customer cannot be POSTed straight back to the return route.
     */
    protected function bridgeUrl(string $returnUrl): string
    {
        return route('api.billing.checkout.callback', ['gateway' => $this->name()])
            .'?return='.urlencode($returnUrl);
    }

    /**
     * The IPN hash: an MD5 over the posted fields named in `verify_key`, each
     * appended in order, plus the MD5 of the store password.
     *
     * Computed over the fields SSLCommerz itself nominates rather than over the
     * whole body, so an attacker cannot weaken the check by adding fields.
     */
    protected function verifiesIpn(Request $request): bool
    {
        $signature = $request->input('verify_sign');
        $keys = $request->input('verify_key');

        if (! is_string($signature) || ! is_string($keys) || $keys === '') {
            return false;
        }

        $fields = [];

        foreach (explode(',', $keys) as $key) {
            $key = trim($key);
            $value = $request->input($key);

            $fields[$key] = is_scalar($value) ? (string) $value : '';
        }

        $fields['store_passwd'] = md5($this->credential('store_password'));

        ksort($fields);

        $hash = [];

        foreach ($fields as $key => $value) {
            $hash[] = $key.'='.$value;
        }

        return hash_equals(md5(implode('&', $hash)), $signature);
    }

    protected function errorFrom(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $message = $body['failedreason'] ?? $body['errorReason'] ?? $body['error'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }
}
