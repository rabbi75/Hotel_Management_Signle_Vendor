<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\CancelSubscription;
use App\Modules\Billing\Actions\ResumeSubscription;
use App\Modules\Billing\Actions\Subscribe;
use App\Modules\Billing\Actions\SwapPlan;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Gateways\ManualGateway;
use App\Modules\Billing\Models\Plan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/*
|------------------------------------------------------------------------------
| The manual gateway must complete a full lifecycle offline.
|------------------------------------------------------------------------------
|
| Http::preventStrayRequests() is the assertion that matters here: if any step
| below reached for a network, the test fails rather than quietly depending on
| credentials CI does not have.
|
*/

beforeEach(function (): void {
    Notification::fake();
    Http::preventStrayRequests();

    config()->set('saas.billing.gateway', 'manual');
});

it('resolves the manual driver by default', function (): void {
    expect(app(GatewayManager::class)->driver())->toBeInstanceOf(ManualGateway::class);
});

it('completes subscribe, swap, cancel and resume with no network', function (): void {
    $company = workspace();

    $starter = Plan::factory()->create(['name' => 'Starter', 'monthly_price' => 1000, 'yearly_price' => 10000]);
    $pro = Plan::factory()->create(['name' => 'Pro', 'monthly_price' => 3000, 'yearly_price' => 30000]);

    // -- Subscribe -----------------------------------------------------------
    $subscription = app(Subscribe::class)->handle($company, $starter, BillingInterval::Monthly);

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan_id)->toBe($starter->id)
        ->and($subscription->gateway)->toBe('manual')
        ->and($subscription->current_period_end)->not->toBeNull();

    // -- Swap ----------------------------------------------------------------
    $subscription = app(SwapPlan::class)->handle($subscription, $pro, BillingInterval::Yearly);

    expect($subscription->fresh()?->plan_id)->toBe($pro->id)
        ->and($subscription->fresh()?->interval)->toBe(BillingInterval::Yearly);

    // -- Cancel at period end ------------------------------------------------
    $subscription = app(CancelSubscription::class)->handle($subscription);

    expect($subscription->cancels_at)->not->toBeNull()
        ->and($subscription->isCancelling())->toBeTrue()
        ->and($subscription->status->grantsAccess())->toBeTrue();

    // -- Resume --------------------------------------------------------------
    $subscription = app(ResumeSubscription::class)->handle($subscription);

    expect($subscription->cancels_at)->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);
});

it('starts a trialing subscription without raising an invoice', function (): void {
    $company = workspace();
    $plan = Plan::factory()->withTrial(14)->create();

    $subscription = app(Subscribe::class)->handle($company, $plan, BillingInterval::Monthly);

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->onTrial())->toBeTrue();

    $this->assertDatabaseCount('invoices', 0);
});

it('cancels immediately when asked to', function (): void {
    $company = workspace();
    $plan = Plan::factory()->create();

    $subscription = app(Subscribe::class)->handle($company, $plan, BillingInterval::Monthly);
    $subscription = app(CancelSubscription::class)->handle($subscription, immediately: true);

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->ended_at)->not->toBeNull()
        ->and($subscription->status->grantsAccess())->toBeFalse();
});

it('refuses to resume a subscription that is not cancelling', function (): void {
    $company = workspace();
    $plan = Plan::factory()->create();

    $subscription = app(Subscribe::class)->handle($company, $plan, BillingInterval::Monthly);

    app(ResumeSubscription::class)->handle($subscription);
})->throws(BillingException::class);

it('advances the lifecycle from the renewal command', function (): void {
    $company = workspace();
    $plan = Plan::factory()->create(['monthly_price' => 1500]);

    $subscription = app(Subscribe::class)->handle($company, $plan, BillingInterval::Monthly);

    // Rewind the period so it has lapsed.
    $subscription->forceFill([
        'current_period_start' => now()->subMonth()->subDay(),
        'current_period_end' => now()->subDay(),
    ])->save();

    $this->artisan('billing:renew')->assertSuccessful();

    $renewed = $subscription->fresh();

    expect($renewed?->current_period_end?->isFuture())->toBeTrue();

    $this->assertDatabaseHas('invoices', ['company_id' => $company->id, 'total' => 1500]);
});
