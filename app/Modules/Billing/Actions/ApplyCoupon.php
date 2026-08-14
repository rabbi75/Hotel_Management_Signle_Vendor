<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Money;
use App\Modules\Company\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApplyCoupon
{
    public function __construct(protected SecurityLogger $security) {}

    /**
     * Redeem a coupon for one workspace.
     *
     * The redemption row's (coupon_id, company_id) unique index — not the
     * in-PHP check — is what actually prevents a double redemption under
     * concurrency; the check exists to produce a good error message.
     */
    public function handle(
        Coupon $coupon,
        Company $company,
        ?Subscription $subscription = null,
        ?Plan $plan = null,
        BillingInterval $interval = BillingInterval::Monthly,
    ): CouponRedemption {
        $plan ??= $subscription?->plan;

        if (! $coupon->isRedeemable($plan)) {
            throw new BillingException($this->reason($coupon, $plan));
        }

        $price = match (true) {
            $subscription instanceof Subscription => $subscription->price(),
            $plan instanceof Plan => $plan->priceFor($interval),
            default => Money::zero(),
        };

        $discount = $coupon->discountFor($price);

        try {
            $redemption = DB::transaction(function () use ($coupon, $company, $subscription, $discount): CouponRedemption {
                $redemption = new CouponRedemption([
                    'coupon_id' => $coupon->id,
                    'subscription_id' => $subscription?->id,
                    'amount_off' => $discount->amount,
                    'currency' => $discount->currency,
                    'redeemed_at' => CarbonImmutable::now(),
                ]);

                $redemption->company_id = $company->id;
                $redemption->save();

                // Atomic increment rather than read-modify-write: two concurrent
                // redemptions must not both see the same starting count.
                Coupon::query()->whereKey($coupon->id)->increment('redeemed_count');

                return $redemption;
            });
        } catch (QueryException) {
            throw new BillingException(__('This coupon has already been redeemed by your workspace.'));
        }

        $this->security->log(
            SecurityEvent::CouponRedeemed,
            Auth::user(),
            __('Coupon :code redeemed.', ['code' => $coupon->code]),
            ['coupon_id' => $coupon->id, 'company_id' => $company->id, 'amount_off' => $discount->amount],
        );

        return $redemption;
    }

    protected function reason(Coupon $coupon, ?Plan $plan): string
    {
        return match (true) {
            ! $coupon->is_active => __('This coupon is no longer active.'),
            $coupon->isExpired() => __('This coupon has expired.'),
            $coupon->isExhausted() => __('This coupon has reached its redemption limit.'),
            $plan instanceof Plan && ! $coupon->appliesToPlan($plan) => __('This coupon does not apply to the selected plan.'),
            default => __('This coupon cannot be redeemed.'),
        };
    }
}
