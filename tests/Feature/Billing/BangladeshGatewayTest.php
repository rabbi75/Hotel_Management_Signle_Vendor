<?php

declare(strict_types=1);

use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\PaymentGatewayConfig;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Billing\Services\GatewayConfigRepository;
use App\Modules\Billing\Services\GatewayRegistry;
use App\Modules\Billing\Support\NagadCrypto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
|------------------------------------------------------------------------------
| The Bangladeshi processors
|------------------------------------------------------------------------------
|
| Driven entirely by Http::fake(), so none of this needs live credentials.
|
| The distinction these tests care most about is the one the whole return leg is
| built on: `paid: false` means the processor said the money did not move, while
| `null` means we could not establish either way. Confusing them either tells a
| customer who paid that they did not, or grants a plan nobody paid for.
|
*/

function bdGateway(string $driver, array $credentials): PaymentGatewayConfig
{
    $config = PaymentGatewayConfig::query()->firstOrNew(['driver' => $driver]);

    $config->fill([
        'label' => $driver,
        'is_enabled' => true,
        'is_test_mode' => true,
        'sort' => 0,
        'credentials' => $credentials,
        'currencies' => ['BDT'],
        'countries' => [],
    ])->save();

    app(GatewayConfigRepository::class)->flush();

    return $config;
}

function bdPlan(): Plan
{
    return Plan::factory()->create([
        'name' => 'Dhaka', 'slug' => 'dhaka-test',
        'monthly_price' => 150000, 'yearly_price' => 1500000,
        'currency' => 'BDT', 'trial_days' => 0,
    ]);
}

/** A return-leg request carrying the parameters the signed URL would. */
function bdReturn(Plan $plan, array $extra = []): Request
{
    return Request::create('/billing/checkout/return', 'GET', array_merge([
        'plan' => $plan->slug,
        'interval' => 'monthly',
        'company' => 'irrelevant-here',
    ], $extra));
}

function bdDriver(string $name): PaymentGateway
{
    return app(GatewayManager::class)->driver($name);
}

beforeEach(function (): void {
    app(GatewayConfigRepository::class)->flush();
    Cache::flush();
});

/*
|------------------------------------------------------------------------------
| Registration
|------------------------------------------------------------------------------
*/

it('registers all four Bangladeshi drivers', function (): void {
    expect(app(GatewayManager::class)->available())
        ->toContain('sslcommerz', 'bkash', 'nagad', 'aamarpay');
});

it('lists BDT as a currency the console can offer', function (): void {
    $currencies = collect(app(GatewayRegistry::class)->all())
        ->flatMap(fn (array $definition): array => $definition['currencies']);

    expect($currencies)->toContain('BDT');
});

/*
|------------------------------------------------------------------------------
| SSLCommerz
|------------------------------------------------------------------------------
*/

it('opens an SSLCommerz checkout with the plan amount in major units', function (): void {
    bdGateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'secret']);
    $plan = bdPlan();

    Http::fake([
        '*gwprocess/v4/api.php' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://pay.example/abc']),
    ]);

    $session = bdDriver('sslcommerz')->checkout(
        workspace(), $plan, BillingInterval::Monthly, 'https://app.test/return', 'https://app.test/cancel',
    );

    expect($session->url)->toBe('https://pay.example/abc')
        ->and($session->gateway)->toBe('sslcommerz');

    Http::assertSent(function ($request): bool {
        return $request['total_amount'] === '1500.00'
            && $request['currency'] === 'BDT'
            // The success leg is POSTed to, so it must go through the bridge
            // rather than straight at the signed return route.
            && str_contains($request['success_url'], '/billing/checkout/callback/sslcommerz');
    });
});

it('verifies an SSLCommerz payment', function (): void {
    bdGateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'secret']);
    $plan = bdPlan();

    Http::fake(['*validationserverAPI.php*' => Http::response([
        'status' => 'VALID', 'amount' => '1500.00', 'currency' => 'BDT',
        'tran_id' => 'sslcommerz_abc', 'bank_tran_id' => 'BANK123',
    ])]);

    $result = bdDriver('sslcommerz')->verifyCheckout(bdReturn($plan, ['ref' => 'val-1']));

    expect($result?->paid)->toBeTrue()
        ->and($result?->amount)->toBe(150000)
        ->and($result?->transactionId)->toBe('BANK123');
});

it('refuses an SSLCommerz reference that settled a different amount', function (): void {
    bdGateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'secret']);
    $plan = bdPlan();

    // A reference replayed from a cheaper completed payment. The status is
    // genuinely VALID, which is exactly why the amount has to be checked too.
    Http::fake(['*validationserverAPI.php*' => Http::response([
        'status' => 'VALID', 'amount' => '10.00', 'currency' => 'BDT', 'tran_id' => 'other',
    ])]);

    $result = bdDriver('sslcommerz')->verifyCheckout(bdReturn($plan, ['ref' => 'val-1']));

    expect($result?->paid)->toBeFalse();
});

it('reports an unverifiable SSLCommerz return as unknown, not as unpaid', function (): void {
    bdGateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'secret']);
    $plan = bdPlan();

    Http::fake(['*validationserverAPI.php*' => Http::response('', 500)]);

    expect(bdDriver('sslcommerz')->verifyCheckout(bdReturn($plan, ['ref' => 'val-1'])))->toBeNull();
});

it('rejects an SSLCommerz IPN whose hash does not match', function (): void {
    bdGateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'secret']);

    $request = Request::create('/api/billing/webhook/sslcommerz', 'POST', [
        'tran_id' => 'sslcommerz_abc',
        'status' => 'VALID',
        'verify_key' => 'tran_id,status',
        'verify_sign' => 'not-the-hash',
    ]);

    expect(bdDriver('sslcommerz')->handleWebhook($request))->toBeNull();
});

it('accepts an SSLCommerz IPN with a correct hash', function (): void {
    bdGateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'secret']);

    $fields = ['tran_id' => 'sslcommerz_abc', 'status' => 'VALID', 'store_passwd' => md5('secret')];
    ksort($fields);

    $pairs = [];

    foreach ($fields as $key => $value) {
        $pairs[] = $key.'='.$value;
    }

    $request = Request::create('/api/billing/webhook/sslcommerz', 'POST', [
        'tran_id' => 'sslcommerz_abc',
        'status' => 'VALID',
        'verify_key' => 'tran_id,status',
        'verify_sign' => md5(implode('&', $pairs)),
    ]);

    $event = bdDriver('sslcommerz')->handleWebhook($request);

    expect($event?->type)->toBe('invoice.payment_succeeded');
});

/*
|------------------------------------------------------------------------------
| bKash
|------------------------------------------------------------------------------
*/

it('grants a bKash token once and reuses it', function (): void {
    bdGateway('bkash', ['app_key' => 'k', 'app_secret' => 's', 'username' => 'u', 'password' => 'p']);
    $plan = bdPlan();

    Http::fake([
        '*token/grant' => Http::response(['id_token' => 'tok_123']),
        '*checkout/create' => Http::response(['bkashURL' => 'https://bkash.test/pay', 'paymentID' => 'PAY1']),
    ]);

    $driver = bdDriver('bkash');
    $driver->checkout(workspace(), $plan, BillingInterval::Monthly, 'https://app.test/return', 'https://app.test/cancel');
    $driver->checkout(workspace(), $plan, BillingInterval::Monthly, 'https://app.test/return', 'https://app.test/cancel');

    // The grant endpoint is rate-limited; two checkouts must not mean two grants.
    Http::assertSentCount(3);
});

it('treats an already-executed bKash payment as paid rather than failed', function (): void {
    bdGateway('bkash', ['app_key' => 'k', 'app_secret' => 's', 'username' => 'u', 'password' => 'p']);
    $plan = bdPlan();

    Http::fake([
        '*token/grant' => Http::response(['id_token' => 'tok_123']),
        // What bKash answers when execute is called a second time.
        '*checkout/execute' => Http::response(['statusCode' => '2029', 'statusMessage' => 'Duplicate execution'], 200),
        '*payment/status' => Http::response([
            'transactionStatus' => 'Completed', 'amount' => '1500.00', 'currency' => 'BDT', 'trxID' => 'TRX9',
        ]),
    ]);

    $result = bdDriver('bkash')->verifyCheckout(bdReturn($plan, ['paymentID' => 'PAY1']));

    expect($result?->paid)->toBeTrue()
        ->and($result?->transactionId)->toBe('TRX9');
});

it('reports a cancelled bKash checkout without calling execute', function (): void {
    bdGateway('bkash', ['app_key' => 'k', 'app_secret' => 's', 'username' => 'u', 'password' => 'p']);
    $plan = bdPlan();

    Http::fake();

    $result = bdDriver('bkash')->verifyCheckout(bdReturn($plan, ['paymentID' => 'PAY1', 'status' => 'cancel']));

    expect($result?->paid)->toBeFalse();
    Http::assertNothingSent();
});

it('does not cache a failed bKash token grant', function (): void {
    bdGateway('bkash', ['app_key' => 'k', 'app_secret' => 's', 'username' => 'u', 'password' => 'p']);

    // A rejection, then the same credentials accepted — the operator having
    // fixed them in between. A cached failure would keep reporting the old
    // answer for the whole token TTL.
    Http::fake(['*token/grant' => Http::sequence()
        ->push(['errorMessage' => 'Invalid app key'], 401)
        ->push(['id_token' => 'tok_ok'], 200),
    ]);

    $driver = bdDriver('bkash');

    expect($driver->ping())->not->toBeNull()
        ->and($driver->ping())->toBeNull();
});

/*
|------------------------------------------------------------------------------
| Nagad
|------------------------------------------------------------------------------
*/

it('round-trips a payload through the Nagad RSA envelope', function (): void {
    ['public' => $public, 'private' => $private] = nagadKeyPair();

    // Both directions use the same pair here; in production the two keys belong
    // to different parties, but the algorithms are the ones being checked.
    $crypto = new NagadCrypto($public, $private);
    $payload = ['merchantId' => 'M1', 'orderId' => 'O1'];

    expect($crypto->decrypt($crypto->encrypt($payload)))->toBe($payload)
        ->and($crypto->verify($payload, $crypto->sign($payload)))->toBeTrue()
        ->and($crypto->verify(['merchantId' => 'M2'], $crypto->sign($payload)))->toBeFalse();
});

it('accepts a Nagad key given as bare base64, without the PEM armour', function (): void {
    ['public' => $public, 'private' => $private] = nagadKeyPair();

    $strip = static fn (string $pem): string => trim(preg_replace('/-----[^-]+-----|\s+/', '', $pem) ?? '');

    $crypto = new NagadCrypto($strip($public), $strip($private));

    expect($crypto->decrypt($crypto->encrypt(['a' => 'b'])))->toBe(['a' => 'b']);
});

it('returns null rather than throwing when a Nagad blob cannot be read', function (): void {
    ['public' => $public, 'private' => $private] = nagadKeyPair();

    expect((new NagadCrypto($public, $private))->decrypt('not-base64-at-all!!'))->toBeNull();
});

it('refuses to pretend a Nagad refund happened', function (): void {
    bdGateway('nagad', ['merchant_id' => 'M', 'merchant_number' => '01', 'public_key' => 'x', 'private_key' => 'y']);

    $transaction = new Transaction(['gateway' => 'nagad', 'gateway_id' => 'ref', 'amount' => 1000, 'currency' => 'BDT']);

    expect(fn () => bdDriver('nagad')->refund($transaction))->toThrow(BillingException::class);
});

it('verifies a Nagad payment', function (): void {
    bdGateway('nagad', ['merchant_id' => 'M', 'merchant_number' => '01', 'public_key' => 'x', 'private_key' => 'y']);
    $plan = bdPlan();

    Http::fake(['*verify/payment/*' => Http::response([
        'status' => 'Success', 'amount' => '1500.00', 'issuerPaymentRefNo' => 'ISS1',
    ])]);

    $result = bdDriver('nagad')->verifyCheckout(bdReturn($plan, ['payment_ref_id' => 'REF1']));

    expect($result?->paid)->toBeTrue()
        ->and($result?->currency)->toBe('BDT');
});

/*
|------------------------------------------------------------------------------
| aamarPay
|------------------------------------------------------------------------------
*/

it('opens an aamarPay checkout and resolves a relative payment url', function (): void {
    bdGateway('aamarpay', ['store_id' => 'store', 'signature_key' => 'sig']);
    $plan = bdPlan();

    Http::fake(['*jsonpost.php' => Http::response(['payment_url' => '/paynow.php?track=abc'])]);

    $session = bdDriver('aamarpay')->checkout(
        workspace(), $plan, BillingInterval::Monthly, 'https://app.test/return', 'https://app.test/cancel',
    );

    expect($session->url)->toBe('https://sandbox.aamarpay.com/paynow.php?track=abc');
});

it('verifies an aamarPay payment', function (): void {
    bdGateway('aamarpay', ['store_id' => 'store', 'signature_key' => 'sig']);
    $plan = bdPlan();

    Http::fake(['*trxcheck*' => Http::response([
        'pay_status' => 'Successful', 'amount' => '1500.00', 'currency' => 'BDT', 'bank_txn' => 'BT1',
    ])]);

    $result = bdDriver('aamarpay')->verifyCheckout(bdReturn($plan, ['ref' => 'aamarpay_abc']));

    expect($result?->paid)->toBeTrue()
        ->and($result?->transactionId)->toBe('BT1');
});

it('reports a failed aamarPay payment as unpaid, not unknown', function (): void {
    bdGateway('aamarpay', ['store_id' => 'store', 'signature_key' => 'sig']);
    $plan = bdPlan();

    Http::fake(['*trxcheck*' => Http::response(['pay_status' => 'Failed', 'amount' => '1500.00'])]);

    $result = bdDriver('aamarpay')->verifyCheckout(bdReturn($plan, ['ref' => 'aamarpay_abc']));

    expect($result)->not->toBeNull()
        ->and($result?->paid)->toBeFalse();
});

it('trusts nothing in an unsigned aamarPay notification', function (): void {
    bdGateway('aamarpay', ['store_id' => 'store', 'signature_key' => 'sig']);

    // aamarPay's IPN carries nothing a merchant can verify offline, so acting on
    // it would mean acting on an unauthenticated body.
    $request = Request::create('/api/billing/webhook/aamarpay', 'POST', ['pay_status' => 'Successful']);

    expect(bdDriver('aamarpay')->handleWebhook($request))->toBeNull();
});

/**
 * A throwaway RSA pair, so the crypto tests need no fixtures on disk.
 *
 * @return array{public: string, private: string}
 */
function nagadKeyPair(): array
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($resource, $private);

    $details = openssl_pkey_get_details($resource);

    return ['public' => $details['key'], 'private' => $private];
}
