<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\HotelPos\Models\Restaurant;

function subscribeForPos(): \App\Modules\Company\Models\Company
{
    config(['saas.billing.enabled' => true]);

    $company = workspace();

    $plan = Plan::factory()->create([
        'entitlements' => ['hotel_pos', 'hotel_management'],
        'limits' => ['seats' => 25, 'hotels' => 3, 'rooms' => 150],
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

it('shows the restaurant POS stub page', function (): void {
    \Spatie\Permission\Models\Permission::findOrCreate('hotel_pos.view', 'web');

    $company = subscribeForPos();
    $member = memberWith(['hotel_pos.view'], $company);

    $hotel = new Hotel([
        'name' => 'City Hotel',
        'status' => HotelStatus::Active,
        'is_active' => true,
    ]);
    $hotel->company_id = $company->id;
    $hotel->save();

    $restaurant = new Restaurant([
        'hotel_id' => $hotel->id,
        'name' => 'Lobby Bistro',
        'code' => 'BISTRO',
        'is_active' => true,
    ]);
    $restaurant->company_id = $company->id;
    $restaurant->save();

    actingAsMember($member, $company)
        ->get(route('restaurants.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'hotel-pos/index')
        ->assertJsonPath('props.restaurants.0.name', 'Lobby Bistro');
});

it('blocks POS routes when the plan lacks hotel_pos', function (): void {
    config(['saas.billing.enabled' => true]);
    $company = workspace();
    $plan = Plan::factory()->create(['entitlements' => ['hotel_management']]);
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

    \Spatie\Permission\Models\Permission::findOrCreate('hotel_pos.view', 'web');
    $member = memberWith(['hotel_pos.view'], $company);

    actingAsMember($member, $company)
        ->get(route('restaurants.index'), inertiaHeaders())
        ->assertStatus(402);
});
