<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PaymentGatewayConfig;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Billing\Services\GatewayConfigRepository;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;

/*
|------------------------------------------------------------------------------
| Hosted checkout
|------------------------------------------------------------------------------
|
| The return leg is the dangerous one. It is a URL the customer's own browser is
| sent to, and it names a plan — so if it could be forged, anyone could grant
| themselves an Enterprise subscription by typing an address.
|
| Two independent things protect it, and these tests cover both: the signature
| proves the parameters are ours, and the driver's own verification proves the
| money moved.
|
*/

function enableGateway(string $driver, array $attributes = []): PaymentGatewayConfig
{
    $config = PaymentGatewayConfig::query()->firstOrNew(['driver' => $driver]);

    $config->fill([
        'label' => ucfirst($driver),
        'is_enabled' => true,
        'is_test_mode' => false,
        'sort' => 0,
        'credentials' => [],
        'currencies' => [],
        'countries' => [],
    ])->fill($attributes)->save();

    return $config;
}

function paidPlan(string $currency = 'USD'): Plan
{
    return Plan::factory()->create([
        'name' => 'Checkout Test',
        'slug' => 'checkout-test',
        'monthly_price' => 4900,
        'yearly_price' => 49000,
        'currency' => $currency,
        'trial_days' => 0,
    ]);
}

beforeEach(function (): void {
    app(GatewayConfigRepository::class)->flush();
});

it('refuses an unsigned return', function (): void {
    $company = workspace();
    $member = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();
    $plan = paidPlan();
    enableGateway('manual');

    // Exactly the parameters a real checkout carries — but hand-typed, with no
    // signature. This is the attack the signature exists to stop.
    actingAsMember($member, $company)
        ->get(route('billing.checkout.return', [
            'gateway' => 'manual',
            'plan' => $plan->slug,
            'interval' => 'monthly',
            'company' => $company->uuid,
        ]))
        ->assertForbidden();

    expect(Subscription::query()->withoutGlobalScope(CompanyScope::class)->count())->toBe(0);
});

it('refuses a signed return whose parameters were tampered with', function (): void {
    $company = workspace();
    $member = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();
    $cheap = paidPlan();
    enableGateway('manual');

    $expensive = Plan::factory()->create([
        'name' => 'Enterprise', 'slug' => 'enterprise-test',
        'monthly_price' => 99900, 'yearly_price' => 999000, 'currency' => 'USD',
    ]);

    $url = URL::temporarySignedRoute('billing.checkout.return', now()->addHour(), [
        'gateway' => 'manual',
        'plan' => $cheap->slug,
        'interval' => 'monthly',
        'company' => $company->uuid,
    ]);

    // Swap the cheap plan for the expensive one, keeping the signature.
    $tampered = str_replace($cheap->slug, $expensive->slug, $url);

    actingAsMember($member, $company)->get($tampered)->assertForbidden();

    expect(Subscription::query()->withoutGlobalScope(CompanyScope::class)->count())->toBe(0);
});

it('refuses a checkout issued for another workspace', function (): void {
    $company = workspace();
    $member = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();
    $plan = paidPlan();
    enableGateway('manual');

    // Correctly signed, but naming a different workspace than the session's.
    $url = URL::temporarySignedRoute('billing.checkout.return', now()->addHour(), [
        'gateway' => 'manual',
        'plan' => $plan->slug,
        'interval' => 'monthly',
        'company' => 'some-other-workspace-uuid',
    ]);

    actingAsMember($member, $company)->get($url)->assertRedirect(route('billing.plans'));

    expect(Subscription::query()->withoutGlobalScope(CompanyScope::class)->count())->toBe(0);
});

it('completes a subscription on a valid return', function (): void {
    $company = workspace();
    $member = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();
    $plan = paidPlan();
    enableGateway('manual');

    $url = URL::temporarySignedRoute('billing.checkout.return', now()->addHour(), [
        'gateway' => 'manual',
        'plan' => $plan->slug,
        'interval' => 'monthly',
        'company' => $company->uuid,
    ]);

    // Appended rather than signed, which is how a real processor delivers its
    // reference: the return route names it as an ignored parameter precisely so
    // that a URL it was added to still validates.
    $url .= '&reference=manual_ref_test';

    actingAsMember($member, $company)->get($url)->assertRedirect(route('billing.index'));

    $subscription = Subscription::query()->withoutGlobalScope(CompanyScope::class)->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->plan_id)->toBe($plan->id)
        ->and($subscription->gateway)->toBe('manual');
});

it('leaves the invoice open when the offline driver reports no payment', function (): void {
    $company = workspace();
    $member = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();
    $plan = paidPlan();
    enableGateway('manual');

    $url = URL::temporarySignedRoute('billing.checkout.return', now()->addHour(), [
        'gateway' => 'manual',
        'plan' => $plan->slug,
        'interval' => 'monthly',
        'company' => $company->uuid,
    ]);

    // Appended rather than signed, which is how a real processor delivers its
    // reference: the return route names it as an ignored parameter precisely so
    // that a URL it was added to still validates.
    $url .= '&reference=manual_ref_test';

    actingAsMember($member, $company)->get($url);

    // Nothing was captured, so no charge is recorded and the invoice stays
    // collectable. Claiming payment here would corrupt the revenue figures.
    expect(Transaction::query()->withoutGlobalScope(CompanyScope::class)->count())->toBe(0);

    $invoice = Invoice::query()->withoutGlobalScope(CompanyScope::class)->first();

    if ($invoice !== null) {
        expect($invoice->status)->toBe(InvoiceStatus::Open);
    }
});

it('does not grant a second subscription from a replayed return', function (): void {
    $company = workspace();
    $member = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();
    $plan = paidPlan();
    enableGateway('manual');

    $url = URL::temporarySignedRoute('billing.checkout.return', now()->addHour(), [
        'gateway' => 'manual',
        'plan' => $plan->slug,
        'interval' => 'monthly',
        'company' => $company->uuid,
    ]);

    // Appended rather than signed, which is how a real processor delivers its
    // reference: the return route names it as an ignored parameter precisely so
    // that a URL it was added to still validates.
    $url .= '&reference=manual_ref_test';

    actingAsMember($member, $company)->get($url);
    // The signature is still valid, so the URL can be replayed — the guard is
    // that the workspace already has a live subscription.
    actingAsMember($member, $company)->get($url)->assertRedirect(route('billing.index'));

    expect(Subscription::query()->withoutGlobalScope(CompanyScope::class)->count())->toBe(1);
});

it('redirects a guest away from the return leg', function (): void {
    get(route('billing.checkout.return', [
        'gateway' => 'manual',
        'plan' => 'anything',
        'interval' => 'monthly',
        'company' => 'x',
    ]))->assertRedirect(route('login'));
});
