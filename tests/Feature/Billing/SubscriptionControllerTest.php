<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;

beforeEach(function (): void {
    Notification::fake();
});

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('billing.index'))->assertRedirect(route('login'));
});

it('forbids a member without billing.view', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('billing.index'), inertiaHeaders())
        ->assertForbidden();
});

it('renders the billing home with no subscription', function (): void {
    $company = workspace();
    $viewer = memberWith(['billing.view'], $company)->refresh();

    actingAsMember($viewer, $company)
        ->get(route('billing.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'billing/index')
        ->assertJsonPath('props.subscription', null);
});

it('shows only the active workspace subscription', function (): void {
    $company = workspace();
    $other = workspace();

    $plan = Plan::factory()->create();
    Subscription::factory()->forCompany($other)->create(['plan_id' => $plan->id]);

    $viewer = memberWith(['billing.view'], $company)->refresh();

    actingAsMember($viewer, $company)
        ->get(route('billing.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.subscription', null);
});

it('subscribes a workspace to a plan', function (): void {
    $company = workspace();
    $buyer = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();
    $plan = Plan::factory()->create(['monthly_price' => 2500]);

    completeCheckout($buyer, $company, $plan->slug, BillingInterval::Monthly->value);

    $this->assertDatabaseHas('subscriptions', [
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active->value,
        'interval' => BillingInterval::Monthly->value,
    ]);

    // A paid, non-trial plan raises its first invoice immediately.
    $this->assertDatabaseHas('invoices', [
        'company_id' => $company->id,
        'total' => 2500,
    ]);
});

it('rejects a subscription to an unknown plan', function (): void {
    $company = workspace();
    $buyer = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();

    actingAsMember($buyer, $company)
        ->post(route('billing.subscribe'), ['plan' => 'nope', 'interval' => 'monthly'])
        ->assertSessionHasErrors('plan');
});

it('rejects an invalid billing interval', function (): void {
    $company = workspace();
    $buyer = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();
    $plan = Plan::factory()->create();

    actingAsMember($buyer, $company)
        ->post(route('billing.subscribe'), ['plan' => $plan->slug, 'interval' => 'weekly'])
        ->assertSessionHasErrors('interval');
});

it('forbids subscribing without the billing.subscribe permission', function (): void {
    $company = workspace();
    $member = memberWith(['billing.view'], $company)->refresh();
    $plan = Plan::factory()->create();

    actingAsMember($member, $company)
        ->post(route('billing.subscribe'), ['plan' => $plan->slug, 'interval' => 'monthly'])
        ->assertForbidden();
});

it('refuses a second subscription while one is running', function (): void {
    $company = workspace();
    $buyer = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();
    $plan = Plan::factory()->create();

    Subscription::factory()->forCompany($company)->create(['plan_id' => $plan->id]);

    actingAsMember($buyer, $company)
        ->post(route('billing.subscribe'), ['plan' => $plan->slug, 'interval' => 'monthly'])
        ->assertSessionHas('error');

    expect(Subscription::query()->where('company_id', $company->id)->count())->toBe(1);
});

it('will not let one workspace cancel another workspace subscription', function (): void {
    $company = workspace();
    $other = workspace();

    $foreign = Subscription::factory()
        ->forCompany($other)
        ->create(['plan_id' => Plan::factory()->create()->id]);

    $canceller = memberWith(['billing.view', 'billing.cancel'], $company)->refresh();

    actingAsMember($canceller, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('billing.cancel', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()?->status)->toBe(SubscriptionStatus::Active);
});

it('requires password confirmation before cancelling', function (): void {
    $company = workspace();
    $canceller = memberWith(['billing.view', 'billing.cancel'], $company)->refresh();

    $subscription = Subscription::factory()
        ->forCompany($company)
        ->create(['plan_id' => Plan::factory()->create()->id]);

    actingAsMember($canceller, $company)
        ->delete(route('billing.cancel', $subscription))
        ->assertRedirect(route('password.confirm'));
});

it('lists the plan picker with proration previews', function (): void {
    $company = workspace();
    $viewer = memberWith(['billing.view'], $company)->refresh();

    $current = Plan::factory()->create(['monthly_price' => 1000, 'sort' => 1]);
    $upgrade = Plan::factory()->create(['monthly_price' => 3000, 'sort' => 2]);

    Subscription::factory()->forCompany($company)->create(['plan_id' => $current->id]);

    $response = actingAsMember($viewer, $company)
        ->get(route('billing.plans'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'billing/plans');

    expect($response->json('props.previews'))
        ->toHaveKey("{$upgrade->slug}:monthly")
        ->and($response->json("props.previews.{$upgrade->slug}:monthly.prorated"))->toBeTrue();
});
