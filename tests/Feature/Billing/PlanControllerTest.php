<?php

declare(strict_types=1);

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;

use function Pest\Laravel\get;

beforeEach(function (): void {
    test()->markTestSkipped('SaaS plan catalogue is disabled in single-vendor mode.');
});

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('admin.plans.index'))->assertRedirect(route('admin.login'));
});

it('keeps a tenant member out of the plans console', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    // Not on the admin guard — bounced to the admin login, never the page.
    actingAsMember($member, $company)
        ->get(route('admin.plans.index'))
        ->assertRedirect(route('admin.login'));
});

it('forbids a support admin from managing plans', function (): void {
    actingAsAdmin(platformAdmin('support'))
        ->post(route('admin.plans.store'), [
            'name' => 'Nope',
            'monthly_price' => '1.00',
            'yearly_price' => '10.00',
            'currency' => 'USD',
            'trial_days' => 0,
        ])
        ->assertForbidden();
});

it('lists plans for an administrator', function (): void {
    $company = workspace();
    $admin = platformAdmin();

    Plan::factory()->create(['name' => 'Starter']);

    $response = actingAsAdmin($admin)
        ->get(route('admin.plans.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'admin/plans/index');

    expect(collect($response->json('props.table.rows'))->pluck('name')->all())->toContain('Starter');
});

it('creates a plan, converting prices to minor units', function (): void {
    $company = workspace();
    $admin = platformAdmin();

    actingAsAdmin($admin)
        ->post(route('admin.plans.store'), [
            'name' => 'Growth',
            'monthly_price' => '29.99',
            'yearly_price' => '299.00',
            'currency' => 'USD',
            'trial_days' => 14,
            'features' => ['Everything in Starter', 'Priority support'],
            'limits' => ['seats' => 25],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('plans', [
        'name' => 'Growth',
        'slug' => 'growth',
        'monthly_price' => 2999,
        'yearly_price' => 29900,
    ]);
});

it('rejects a plan with no name', function (): void {
    $company = workspace();
    $admin = platformAdmin();

    actingAsAdmin($admin)
        ->post(route('admin.plans.store'), [
            'name' => '',
            'monthly_price' => '10.00',
            'yearly_price' => '100.00',
            'currency' => 'USD',
            'trial_days' => 0,
        ])
        ->assertSessionHasErrors('name');
});

it('leaves the gateway price matrix alone when the request omits it', function (): void {
    $company = workspace();
    $admin = platformAdmin();

    $plan = Plan::factory()->create([
        'gateway_prices' => ['stripe' => ['monthly' => 'price_123']],
    ]);

    actingAsAdmin($admin)
        ->patch(route('admin.plans.update', $plan), [
            'name' => 'Renamed',
            'monthly_price' => '19.00',
            'yearly_price' => '190.00',
            'currency' => 'USD',
            'trial_days' => 0,
        ])
        ->assertRedirect();

    expect($plan->fresh()?->gateway_prices)->toBe(['stripe' => ['monthly' => 'price_123']])
        ->and($plan->fresh()?->name)->toBe('Renamed');
});

it('refuses to delete a plan that still has subscribers', function (): void {
    $company = workspace();
    $admin = platformAdmin();

    $plan = Plan::factory()->create();
    Subscription::factory()->forCompany($company)->create(['plan_id' => $plan->id]);

    actingAsAdmin($admin)
        ->delete(route('admin.plans.destroy', $plan))
        ->assertSessionHas('error');

    expect(Plan::query()->whereKey($plan->id)->exists())->toBeTrue();
});
