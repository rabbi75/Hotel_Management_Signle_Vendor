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
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * aamarPay — a Bangladeshi aggregator covering the mobile wallets and cards.
 *
 * The same shape as SSLCommerz and with the same POST-back return leg, so it
 * goes through the callback bridge too. Simpler in every other respect: one
 * JSON call opens the checkout and one GET confirms it.
 *
 * aamarPay has no public refund API — refunds are raised in their merchant
 * panel — so `supports_refunds` is false in the registry and `refund()` says so
 * rather than silently reporting a success that never happened.
 */
class AamarPayGateway extends HostedCheckoutGateway
{
    public function name(): string
    {
        return 'aamarpay';
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
        $bridge = route('api.billing.checkout.callback', ['gateway' => $this->name()])
            .'?return='.urlencode($returnUrl);

        $response = $this->http()->post('/jsonpost.php', [
            'store_id' => $this->credential('store_id'),
            'signature_key' => $this->credential('signature_key'),
            'tran_id' => $reference,

            // Major units, like SSLCommerz.
            'amount' => $this->major($this->amountFor($plan, $interval)),
            'currency' => $this->currencyFor($plan),
            'desc' => $plan->name,

            'success_url' => $bridge,
            'fail_url' => $bridge,
            'cancel_url' => $cancelUrl,

            'cus_name' => $owner->name,
            'cus_email' => $owner->email,
            'cus_phone' => $owner->phone ?? 'N/A',
            'cus_add1' => 'N/A',
            'cus_city' => 'N/A',
            'cus_country' => 'Bangladesh',

            'opt_a' => $company->uuid,
            'opt_b' => $plan->slug,
            'type' => 'json',
        ]);

        $url = $response->json('payment_url');

        if ($response->failed() || ! is_string($url) || $url === '') {
            throw new BillingException(
                $this->errorFrom($response->json()) ?? __('aamarPay refused to open a checkout.'),
            );
        }

        // A relative path is returned in some responses; the host is the same one.
        return new CheckoutSession(
            gateway: $this->name(),
            url: str_starts_with($url, 'http') ? $url : rtrim($this->baseUrl(), '/').'/'.ltrim($url, '/'),
            reference: $reference,
        );
    }

    /**
     * aamarPay posts our own `mer_txnid` back, which is also what the
     * verification endpoint takes.
     */
    public function referenceFromCallback(Request $request): ?string
    {
        foreach (['mer_txnid', 'tran_id'] as $field) {
            $value = $request->input($field);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function verifyCheckout(Request $request): ?CheckoutResult
    {
        $reference = $request->query('ref');

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $response = $this->http()->get('/api/v1/trxcheck/request.php', [
            'request_id' => $reference,
            'store_id' => $this->credential('store_id'),
            'signature_key' => $this->credential('signature_key'),
            'type' => 'json',
        ]);

        if ($response->failed()) {
            return null;
        }

        $status = $response->json('pay_status');

        if (! is_string($status)) {
            return null;
        }

        $paid = $status === 'Successful';
        $amount = (int) round(((float) $response->json('amount', 0)) * 100);
        $currency = is_string($response->json('currency')) ? strtoupper($response->json('currency')) : 'BDT';

        if ($paid && ! $this->matchesExpectedCharge($request, $amount, $currency)) {
            return new CheckoutResult(
                gateway: $this->name(),
                reference: $reference,
                paid: false,
                amount: $amount,
                currency: $currency,
                failureReason: __('The amount aamarPay settled does not match this plan.'),
            );
        }

        return new CheckoutResult(
            gateway: $this->name(),
            reference: $reference,
            paid: $paid,
            amount: $amount,
            currency: $currency,
            transactionId: is_string($response->json('bank_txn')) ? $response->json('bank_txn') : $reference,
            failureReason: $paid ? null : $status,
        );
    }

    /**
     * aamarPay's IPN carries no signature the merchant can verify offline, so
     * nothing here is trusted. The return leg's own verification call is the
     * confirmation, and returning null makes the controller answer 400 rather
     * than acting on an unauthenticated body.
     */
    public function handleWebhook(Request $request): ?GatewayWebhookData
    {
        return null;
    }

    public function refund(Transaction $transaction, ?int $amount = null): GatewayRefundData
    {
        throw new BillingException(
            __('aamarPay refunds are raised in the aamarPay merchant panel; this one cannot be issued from here.'),
        );
    }

    public function ping(): ?string
    {
        // Checking a reference that cannot exist: recognised credentials come
        // back with a "not found" style body, unrecognised ones with an error.
        $response = $this->http()->get('/api/v1/trxcheck/request.php', [
            'request_id' => 'ping',
            'store_id' => $this->credential('store_id'),
            'signature_key' => $this->credential('signature_key'),
            'type' => 'json',
        ]);

        if ($response->failed()) {
            return 'HTTP '.$response->status();
        }

        return $this->errorFrom($response->json());
    }

    protected function baseUrl(): string
    {
        return $this->isTestMode()
            ? 'https://sandbox.aamarpay.com'
            : 'https://secure.aamarpay.com';
    }

    protected function errorFrom(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        // aamarPay reports a rejected call as a `result` of false alongside a
        // message; a recognised-but-unknown reference is not an error.
        $message = $body['message'] ?? $body['error'] ?? null;

        if (! is_string($message) || $message === '') {
            return null;
        }

        return str_contains(strtolower($message), 'not found') ? null : $message;
    }
}
