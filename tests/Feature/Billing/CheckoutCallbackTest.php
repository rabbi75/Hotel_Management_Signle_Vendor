<?php

declare(strict_types=1);

use App\Modules\Billing\Models\PaymentGatewayConfig;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\GatewayConfigRepository;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\post;

/*
|------------------------------------------------------------------------------
| The POST-callback bridge
|------------------------------------------------------------------------------
|
| SSLCommerz and aamarPay submit a form to the success URL. That is a cross-site
| POST: no CSRF token, and no SameSite=lax session cookie either — so it cannot
| land on the return route directly.
|
| The bridge exists to turn that POST into a GET the browser makes itself. It
| grants nothing. Its whole security surface is the `return` parameter, which an
| attacker controls: unchecked, this endpoint is an open redirect sitting on a
| billing URL, which is exactly the shape a phishing link wants.
|
*/

function callbackReturnUrl(string $plan = 'dhaka-test', string $company = 'ws-uuid'): string
{
    return URL::temporarySignedRoute('billing.checkout.return', now()->addHour(), [
        'gateway' => 'sslcommerz',
        'plan' => $plan,
        'interval' => 'monthly',
        'company' => $company,
    ]);
}

beforeEach(function (): void {
    app(GatewayConfigRepository::class)->flush();
});

it('bounces a posted callback to the signed return url, carrying the reference', function (): void {
    $return = callbackReturnUrl();

    $response = post(
        route('api.billing.checkout.callback', ['gateway' => 'sslcommerz']).'?return='.urlencode($return),
        ['val_id' => 'VAL-123', 'status' => 'VALID'],
    );

    $response->assertRedirect();

    $location = $response->headers->get('Location');

    expect($location)->toStartWith($return)
        ->and($location)->toContain('ref=VAL-123');
});

it('still redirects when the processor sent no reference', function (): void {
    $return = callbackReturnUrl();

    // The return leg will fail to verify and say so; losing the customer at the
    // bridge with a bare error page would be worse.
    post(
        route('api.billing.checkout.callback', ['gateway' => 'sslcommerz']).'?return='.urlencode($return),
        ['status' => 'FAILED'],
    )->assertRedirect($return);
});

it('refuses an unsigned return url', function (): void {
    $unsigned = route('billing.checkout.return', [
        'gateway' => 'sslcommerz', 'plan' => 'dhaka-test', 'interval' => 'monthly', 'company' => 'ws-uuid',
    ]);

    post(
        route('api.billing.checkout.callback', ['gateway' => 'sslcommerz']).'?return='.urlencode($unsigned),
        ['val_id' => 'VAL-123'],
    )->assertRedirect(route('billing.plans'));
});

it('refuses a return url whose parameters were tampered with', function (): void {
    $return = callbackReturnUrl('dhaka-test');
    $tampered = str_replace('dhaka-test', 'enterprise', $return);

    post(
        route('api.billing.checkout.callback', ['gateway' => 'sslcommerz']).'?return='.urlencode($tampered),
        ['val_id' => 'VAL-123'],
    )->assertRedirect(route('billing.plans'));
});

it('refuses to redirect anywhere but the checkout return route', function (): void {
    // The open-redirect case: an external destination, and an internal one that
    // simply is not the route this endpoint exists to hand off to.
    foreach (['https://evil.example/phish', route('billing.plans')] as $target) {
        post(
            route('api.billing.checkout.callback', ['gateway' => 'sslcommerz']).'?return='.urlencode($target),
            ['val_id' => 'VAL-123'],
        )->assertRedirect(route('billing.plans'));
    }
});

it('refuses a signed url for a different route on this host', function (): void {
    // Signed, ours, and still not the checkout return leg. The signature alone
    // is not the check; the route name is checked too.
    $signed = URL::temporarySignedRoute('billing.checkout.braintree', now()->addHour(), [
        'plan' => 'dhaka-test', 'interval' => 'monthly',
    ]);

    post(
        route('api.billing.checkout.callback', ['gateway' => 'sslcommerz']).'?return='.urlencode($signed),
        ['val_id' => 'VAL-123'],
    )->assertRedirect(route('billing.plans'));
});

it('refuses a callback with no return url at all', function (): void {
    post(route('api.billing.checkout.callback', ['gateway' => 'sslcommerz']), ['val_id' => 'VAL-123'])
        ->assertRedirect(route('billing.plans'));
});

it('grants nothing by itself', function (): void {
    $return = callbackReturnUrl();

    post(
        route('api.billing.checkout.callback', ['gateway' => 'sslcommerz']).'?return='.urlencode($return),
        ['val_id' => 'VAL-123', 'status' => 'VALID'],
    );

    // The bridge only forwards. Everything that decides whether a subscription
    // exists still happens on the return leg, with a session and a verified
    // payment behind it.
    expect(Subscription::query()->withoutGlobalScope(CompanyScope::class)->count())->toBe(0);
});

it('accepts a processor-appended parameter on the return leg', function (): void {
    // Regression cover: the return route signs its own parameters but must
    // tolerate the ones a processor adds, or every hosted driver 403s the
    // customer after they have paid.
    $company = workspace();
    $member = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();

    PaymentGatewayConfig::query()->updateOrCreate(['driver' => 'manual'], [
        'label' => 'Manual', 'is_enabled' => true, 'is_test_mode' => false,
        'sort' => 0, 'credentials' => [], 'currencies' => [], 'countries' => [],
    ]);
    app(GatewayConfigRepository::class)->flush();

    Plan::factory()->create([
        'name' => 'Callback', 'slug' => 'callback-test',
        'monthly_price' => 4900, 'yearly_price' => 49000,
        'currency' => 'USD', 'trial_days' => 0,
    ]);

    $url = URL::temporarySignedRoute('billing.checkout.return', now()->addHour(), [
        'gateway' => 'manual',
        'plan' => 'callback-test',
        'interval' => 'monthly',
        'company' => $company->uuid,
    ]);

    foreach (['ref', 'reference', 'trxref', 'paymentID', 'payment_ref_id', 'status'] as $parameter) {
        actingAsMember($member, $company)
            ->get($url.'&'.$parameter.'=whatever')
            ->assertStatus(302);
    }
});
