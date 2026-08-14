<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\Billing\Database\Seeders\PaymentGatewaySeeder;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\PaymentGatewayConfig;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\GatewayConfigRepository;
use App\Modules\Billing\Services\GatewayRegistry;
use App\Modules\Platform\Http\Controllers\PlatformGatewayController;

use function Pest\Laravel\get;

beforeEach(function (): void {
    (new PaymentGatewaySeeder(
        app(GatewayRegistry::class),
    ))->run();

    app(GatewayConfigRepository::class)->flush();
});

it('redirects a guest to the console login', function (): void {
    get(route('admin.gateways.index'))->assertRedirect(route('admin.login'));
});

it('keeps a tenant user out', function (): void {
    $company = workspace();
    $owner = superAdmin($company);

    actingAsMember($owner, $company)
        ->get(route('admin.gateways.index'))
        ->assertRedirect(route('admin.login'));
});

it('forbids an operator without the manage permission', function (): void {
    actingAsAdmin(platformAdmin('support'))
        ->get(route('admin.gateways.index'))
        ->assertForbidden();
});

it('lists every known processor', function (): void {
    // Counted from the registry rather than hard-coded, so adding a driver does
    // not mean editing a magic number here.
    $expected = count(app(GatewayRegistry::class)->all());

    actingAsAdmin(platformAdminWith(['platform.gateways.manage']))
        ->get(route('admin.gateways.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'admin/billing/gateways')
        ->assertJsonCount($expected, 'props.gateways');
});

it('lists the Bangladeshi processors', function (): void {
    $response = actingAsAdmin(platformAdminWith(['platform.gateways.manage']))
        ->get(route('admin.gateways.index'), inertiaHeaders())
        ->assertOk();

    $drivers = collect($response->json('props.gateways'))->pluck('driver');

    expect($drivers)->toContain('sslcommerz', 'bkash', 'nagad', 'aamarpay');
});

it('accepts an RSA private key as a Nagad credential and never echoes it back', function (): void {
    $admin = platformAdminWith(['platform.gateways.manage']);
    $nagad = PaymentGatewayConfig::query()->where('driver', 'nagad')->firstOrFail();

    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($resource, $pem);

    // A 2048-bit PEM is around 1,700 characters — comfortably past the 500 the
    // credential rule allowed before Nagad needed one.
    expect(strlen($pem))->toBeGreaterThan(500);

    actingAsAdmin($admin)->put(route('admin.gateways.update', $nagad), [
        'is_enabled' => false,
        'is_test_mode' => true,
        'currencies' => ['BDT'],
        'countries' => ['BD'],
        'credentials' => [
            'merchant_id' => 'M123',
            'merchant_number' => '01700000000',
            'public_key' => 'nagad-public',
            'private_key' => $pem,
        ],
    ])->assertSessionHasNoErrors();

    // The trailing newline is dropped in transit, which OpenSSL does not mind;
    // what matters is that the body survived intact rather than being truncated.
    expect(trim((string) $nagad->refresh()->credential('private_key')))->toBe(trim($pem));

    $response = actingAsAdmin($admin)->get(route('admin.gateways.index'), inertiaHeaders());

    $row = collect($response->json('props.gateways'))->firstWhere('driver', 'nagad');

    expect($row['credentials']['private_key']['value'])->toBe(PlatformGatewayController::MASK)
        ->and($row['credentials']['private_key']['is_set'])->toBeTrue();
});

it('never returns a stored secret to the client', function (): void {
    $admin = platformAdminWith(['platform.gateways.manage']);
    $stripe = PaymentGatewayConfig::query()->where('driver', 'stripe')->firstOrFail();

    actingAsAdmin($admin)->put(route('admin.gateways.update', $stripe), [
        'is_enabled' => true,
        'is_test_mode' => true,
        'currencies' => ['USD'],
        'countries' => [],
        'credentials' => [
            'secret_key' => 'sk_test_super_secret',
            'publishable_key' => 'pk_test_public',
            'webhook_secret' => 'whsec_super_secret',
        ],
    ])->assertRedirect();

    app(GatewayConfigRepository::class)->flush();

    $response = actingAsAdmin($admin)
        ->get(route('admin.gateways.index'), inertiaHeaders())
        ->assertOk();

    expect($response->getContent())->not->toContain('sk_test_super_secret')
        ->and($response->getContent())->not->toContain('whsec_super_secret');

    $stripeProps = collect($response->json('props.gateways'))->firstWhere('driver', 'stripe');

    expect($stripeProps['credentials']['secret_key']['value'])->toBe(PlatformGatewayController::MASK)
        ->and($stripeProps['credentials']['secret_key']['is_set'])->toBeTrue()
        // A non-secret field is safe to show, and useful to.
        ->and($stripeProps['credentials']['publishable_key']['value'])->toBe('pk_test_public');
});

it('leaves a stored secret alone when the field is not resubmitted', function (): void {
    $admin = platformAdminWith(['platform.gateways.manage']);
    $stripe = PaymentGatewayConfig::query()->where('driver', 'stripe')->firstOrFail();

    actingAsAdmin($admin)->put(route('admin.gateways.update', $stripe), [
        'is_enabled' => true, 'is_test_mode' => true, 'currencies' => ['USD'], 'countries' => [],
        'credentials' => ['secret_key' => 'sk_test_original'],
    ]);

    // A later save that only changes the currency list must not wipe the key.
    actingAsAdmin($admin)->put(route('admin.gateways.update', $stripe), [
        'is_enabled' => true, 'is_test_mode' => true, 'currencies' => ['USD', 'EUR'], 'countries' => [],
        'credentials' => [],
    ])->assertRedirect();

    app(GatewayConfigRepository::class)->flush();
    $stripe->refresh();

    expect($stripe->credential('secret_key'))->toBe('sk_test_original')
        ->and($stripe->currencies)->toBe(['USD', 'EUR']);
});

it('records the configuration change without recording the credential', function (): void {
    $admin = platformAdminWith(['platform.gateways.manage']);
    $stripe = PaymentGatewayConfig::query()->where('driver', 'stripe')->firstOrFail();

    actingAsAdmin($admin)->put(route('admin.gateways.update', $stripe), [
        'is_enabled' => true, 'is_test_mode' => true, 'currencies' => ['USD'], 'countries' => [],
        'credentials' => ['secret_key' => 'sk_test_never_logged'],
    ]);

    $entry = SecurityLog::query()
        ->where('event', SecurityEvent::PaymentGatewayConfigured->value)
        ->latest('id')
        ->firstOrFail();

    expect($entry->admin_id)->toBe($admin->id)
        ->and($entry->user_id)->toBeNull()
        ->and(json_encode($entry->context))->not->toContain('sk_test_never_logged');
});

it('does not offer a gateway that cannot settle the plan currency', function (): void {
    // Paystack settles NGN and friends, not EUR.
    PaymentGatewayConfig::query()->where('driver', 'paystack')->update([
        'is_enabled' => true,
        'credentials' => encrypt(json_encode(['secret_key' => 'sk', 'public_key' => 'pk'])),
        'currencies' => json_encode(['NGN']),
    ]);

    app(GatewayConfigRepository::class)->flush();

    $eurPlan = Plan::factory()->create(['currency' => 'EUR']);
    $offered = app(GatewayManager::class)->enabledFor($eurPlan);

    expect($offered->keys()->all())->not->toContain('paystack');
});

it('does not offer an enabled gateway that is missing a credential', function (): void {
    PaymentGatewayConfig::query()->where('driver', 'mollie')->update(['is_enabled' => true]);
    app(GatewayConfigRepository::class)->flush();

    $plan = Plan::factory()->create(['currency' => 'EUR']);

    // Enabled but unconfigured: offering it would fail at the worst moment.
    expect(app(GatewayManager::class)->enabledFor($plan)->keys()->all())->not->toContain('mollie');
});
