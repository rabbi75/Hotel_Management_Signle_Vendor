<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;

beforeEach(function (): void {
    config(['saas.billing.enabled' => true]);
});

function companyOnSeatPlan(int $seats): Company
{
    $company = workspace();

    $plan = Plan::factory()->create(['limits' => ['seats' => $seats]]);

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

it('rejects an invitation that would exceed the seat allowance', function (): void {
    // A one-seat plan, and the owner already occupies it.
    $company = companyOnSeatPlan(1);
    $inviter = memberWith(['companies.members.invite'], $company, CompanyRole::Owner);

    actingAsMember($inviter, $company)
        ->post(route('companies.invitations.store'), [
            'email' => 'newcomer@example.com',
            'role' => CompanyRole::Member->value,
        ])
        ->assertSessionHas('error');

    expect($company->invitations()->where('email', 'newcomer@example.com')->exists())->toBeFalse();
});

it('allows an invitation within the seat allowance', function (): void {
    $company = companyOnSeatPlan(10);
    $inviter = memberWith(['companies.members.invite'], $company, CompanyRole::Owner);

    actingAsMember($inviter, $company)
        ->post(route('companies.invitations.store'), [
            'email' => 'newcomer@example.com',
            'role' => CompanyRole::Member->value,
        ])
        ->assertSessionHas('success');

    expect($company->invitations()->where('email', 'newcomer@example.com')->exists())->toBeTrue();
});
