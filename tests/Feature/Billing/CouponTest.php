<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\ApplyCoupon;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Support\Money;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;

beforeEach(function (): void {
    Notification::fake();
});

it('redirects a guest away from the coupon admin', function (): void {
    workspace();

    get(route('admin.coupons.index'))->assertRedirect(route('admin.login'));
});

it('keeps a tenant member out of the coupon console', function (): void {
    $company = workspace();
    $member = memberWith(['billing.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('admin.coupons.index'))
        ->assertRedirect(route('admin.login'));
});

it('creates a percentage coupon', function (): void {
    $company = workspace();
    $admin = platformAdmin();

    actingAsAdmin($admin)
        ->post(route('admin.coupons.store'), [
            'code' => 'LAUNCH20',
            'type' => 'percent',
            'value' => 20,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('coupons', ['code' => 'LAUNCH20', 'type' => 'percent', 'value' => 20]);
});

it('rejects a percentage above 100', function (): void {
    $company = workspace();
    $admin = platformAdmin();

    actingAsAdmin($admin)
        ->post(route('admin.coupons.store'), ['code' => 'TOOMUCH', 'type' => 'percent', 'value' => 150])
        ->assertSessionHasErrors('value');
});

it('stores a fixed coupon in minor units', function (): void {
    $company = workspace();
    $admin = platformAdmin();

    actingAsAdmin($admin)
        ->post(route('admin.coupons.store'), [
            'code' => 'FIVEOFF',
            'type' => 'fixed',
            'value' => '5.00',
            'currency' => 'USD',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('coupons', ['code' => 'FIVEOFF', 'value' => 500]);
});

it('cannot be redeemed past its redemption limit', function (): void {
    $first = workspace();
    $second = workspace();

    $plan = Plan::factory()->create(['monthly_price' => 2000]);
    $coupon = Coupon::factory()->limitedTo(1)->create();

    app(ApplyCoupon::class)->handle($coupon, $first, null, $plan, BillingInterval::Monthly);

    expect($coupon->fresh()?->redeemed_count)->toBe(1);

    app(ApplyCoupon::class)->handle($coupon->fresh(), $second, null, $plan, BillingInterval::Monthly);
})->throws(BillingException::class, 'redemption limit');

it('cannot be redeemed after it expires', function (): void {
    $company = workspace();
    $plan = Plan::factory()->create();
    $coupon = Coupon::factory()->expired()->create();

    app(ApplyCoupon::class)->handle($coupon, $company, null, $plan, BillingInterval::Monthly);
})->throws(BillingException::class, 'expired');

it('cannot be redeemed twice by the same workspace', function (): void {
    $company = workspace();
    $plan = Plan::factory()->create();
    $coupon = Coupon::factory()->create();

    app(ApplyCoupon::class)->handle($coupon, $company, null, $plan, BillingInterval::Monthly);
    app(ApplyCoupon::class)->handle($coupon->fresh(), $company, null, $plan, BillingInterval::Monthly);
})->throws(BillingException::class, 'already been redeemed');

it('does not apply to a plan outside its allow list', function (): void {
    $company = workspace();
    $allowed = Plan::factory()->create();
    $other = Plan::factory()->create();
    $coupon = Coupon::factory()->create(['plan_ids' => [$allowed->id]]);

    expect($coupon->appliesToPlan($allowed))->toBeTrue()
        ->and($coupon->appliesToPlan($other))->toBeFalse();

    app(ApplyCoupon::class)->handle($coupon, $company, null, $other, BillingInterval::Monthly);
})->throws(BillingException::class, 'does not apply');

it('never discounts more than the price itself', function (): void {
    $coupon = Coupon::factory()->fixed(5000)->create();

    $discount = $coupon->discountFor(Money::of(1200, 'USD'));

    expect($discount->amount)->toBe(1200);
});

it('applies a coupon to the first invoice when subscribing', function (): void {
    $company = workspace();
    $buyer = memberWith(['billing.view', 'billing.subscribe'], $company)->refresh();

    $plan = Plan::factory()->create(['monthly_price' => 5000]);
    Coupon::factory()->create(['code' => 'HALF', 'value' => 50]);

    completeCheckout($buyer, $company, $plan->slug, 'monthly', 'half');

    $this->assertDatabaseHas('invoices', [
        'company_id' => $company->id,
        'subtotal' => 5000,
        'discount' => 2500,
        'total' => 2500,
    ]);
});
