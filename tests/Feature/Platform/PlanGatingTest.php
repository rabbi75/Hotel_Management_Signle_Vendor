<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Company\Models\Company;
use App\Support\Navigation\NavigationBuilder;

beforeEach(function (): void {
    // The whole feature is inert unless billing is on; every check short-circuits
    // to "allowed" otherwise.
    config(['saas.billing.enabled' => true]);
});

/**
 * Give the active workspace a live subscription on a plan with the given
 * entitlements, and return the workspace.
 */
function subscribeCompanyTo(array $entitlements, array $limits = []): Company
{
    $company = workspace();

    $plan = Plan::factory()->create([
        'entitlements' => $entitlements,
        'limits' => $limits ?: ['seats' => 25],
    ]);

    $subscription = new Subscription([
        'plan_id' => $plan->id,
        'gateway' => 'manual',
        'status' => SubscriptionStatus::Active,
        'interval' => BillingInterval::Monthly,
        'quantity' => 1,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'dunning_attempts' => 0,
    ]);
    $subscription->company_id = $company->id;
    $subscription->save();

    return $company;
}

it('blocks a feature the plan does not grant', function (): void {
    $company = subscribeCompanyTo(['chat', 'media']);
    $member = memberWith(['ai.use'], $company);

    actingAsMember($member, $company)
        ->get(route('ai.index'))
        ->assertStatus(402);
});

it('allows a feature the plan grants', function (): void {
    $company = subscribeCompanyTo(['chat', 'media', 'ai']);
    $member = memberWith(['ai.use'], $company);

    // 402 would mean the plan gate rejected it; anything else means it passed the
    // gate (the AI page itself may still 200 or redirect on its own terms).
    actingAsMember($member, $company)
        ->get(route('ai.index'))
        ->assertOk();
});

it('hides a feature-gated nav item when the plan lacks it', function (): void {
    $company = subscribeCompanyTo(['chat', 'media']);
    $member = memberWith(['ai.use'], $company);

    $nav = app(NavigationBuilder::class)->for($member);

    $labels = collect($nav)->flatMap(fn (array $section): array => array_column($section['items'], 'label'));

    expect($labels)->not->toContain('AI');
});
