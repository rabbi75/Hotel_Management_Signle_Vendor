<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Company\Actions\CreateCompany;
use App\Modules\Company\DTOs\CompanyData;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    config(['saas.billing.enabled' => true]);

    // A signup plan the enforcement can auto-subscribe new workspaces to.
    config(['saas.billing.signup_plan' => 'starter']);

    Plan::factory()->create(['slug' => 'starter', 'trial_days' => 14, 'is_active' => true]);

    // CreateCompany grants the workspace owner the global `admin` role.
    Role::findOrCreate('admin', 'web');
});

it('auto-subscribes a new workspace to a trial of the signup plan', function (): void {
    $owner = User::factory()->create();

    $company = app(CreateCompany::class)->handle(
        new CompanyData(name: 'Fresh Co'),
        $owner,
    );

    $subscription = $company->activeSubscription()->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->status)->toBe(SubscriptionStatus::Trialing);
    expect($company->hasActiveAccess())->toBeTrue();
});

it('lets a trialing workspace reach the dashboard', function (): void {
    $owner = User::factory()->create();
    $company = app(CreateCompany::class)->handle(
        new CompanyData(name: 'Trialing Co'),
        $owner,
    );

    $member = memberWith(['dashboard.view'], $company);

    actingAsMember($member, $company)
        ->get(route('dashboard'))
        ->assertOk();
});

it('locks a workspace with a lapsed trial and no subscription to the plan picker', function (): void {
    // No subscription, and the signup trial window is in the past.
    $company = workspace();
    $company->forceFill(['trial_ends_at' => now()->subDay()])->save();

    $member = memberWith(['dashboard.view'], $company);

    actingAsMember($member, $company)
        ->get(route('dashboard'))
        ->assertRedirect(route('billing.plans'));
});

it('keeps the plan picker reachable while locked', function (): void {
    $company = workspace();
    $company->forceFill(['trial_ends_at' => now()->subDay()])->save();

    $member = memberWith(['dashboard.view', 'billing.view'], $company);

    actingAsMember($member, $company)
        ->get(route('billing.plans'))
        ->assertOk();
});

it('does not lock when billing is disabled', function (): void {
    config(['saas.billing.enabled' => false]);

    $company = workspace();
    $company->forceFill(['trial_ends_at' => now()->subDay()])->save();

    $member = memberWith(['dashboard.view'], $company);

    actingAsMember($member, $company)
        ->get(route('dashboard'))
        ->assertOk();
});

it('reports access correctly from hasActiveAccess', function (): void {
    $active = Company::factory()->create(['trial_ends_at' => now()->addWeek()]);
    expect($active->hasActiveAccess())->toBeTrue();

    $lapsed = Company::factory()->create(['trial_ends_at' => now()->subWeek()]);
    expect($lapsed->hasActiveAccess())->toBeFalse();
});
