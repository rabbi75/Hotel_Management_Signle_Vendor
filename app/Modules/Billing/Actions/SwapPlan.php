<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\SubscriptionChangedNotification;
use App\Modules\Billing\Support\Money;
use Illuminate\Support\Facades\Auth;

class SwapPlan
{
    public function __construct(
        protected GatewayManager $gateways,
        protected SecurityLogger $security,
    ) {}

    public function handle(Subscription $subscription, Plan $plan, ?BillingInterval $interval = null): Subscription
    {
        $interval ??= $subscription->interval;
        $previous = $subscription->plan;

        if (! $subscription->status->isLive()) {
            throw new BillingException(__('Only a running subscription can be changed.'));
        }

        if ($previous->id === $plan->id && $subscription->interval === $interval) {
            throw new BillingException(__('That is already the current plan.'));
        }

        if (! $plan->is_active) {
            throw new BillingException(__('That plan is not available.'));
        }

        // The subscription's own gateway, not the configured default: a plan
        // granted by hand must not be swapped through Stripe, and a Stripe
        // subscription must not be swapped locally because the default changed.
        $remote = $this->gateways->driver($subscription->gateway)->swapPlan($subscription, $plan, $interval);

        $subscription->fill($remote->toAttributes());
        $subscription->plan_id = $plan->id;
        $subscription->save();

        $this->security->log(
            SecurityEvent::SubscriptionChanged,
            Auth::user(),
            __('Plan changed from :from to :to.', ['from' => $previous->name, 'to' => $plan->name]),
            [
                'company_id' => $subscription->company_id,
                'from_plan_id' => $previous->id,
                'to_plan_id' => $plan->id,
                'interval' => $interval->value,
            ],
        );

        $subscription->company->owner->notify(
            new SubscriptionChangedNotification($subscription->load('plan'), $previous),
        );

        return $subscription;
    }

    /**
     * What the change costs today, without performing it.
     *
     * Unused time on the current plan is credited and the new plan is charged
     * for the same window; the difference is what appears on the next invoice.
     *
     * @return array{credit: Money, charge: Money, due: Money, prorated: bool}
     */
    public function preview(Subscription $subscription, Plan $plan, ?BillingInterval $interval = null): array
    {
        $interval ??= $subscription->interval;
        [$remaining, $total] = $subscription->remainingPeriod();

        $currency = $subscription->plan->currency;

        if ($total === 0) {
            return [
                'credit' => Money::zero($currency),
                'charge' => $plan->priceFor($interval),
                'due' => $plan->priceFor($interval),
                'prorated' => false,
            ];
        }

        $credit = $subscription->price()->prorate($remaining, $total);
        $charge = $plan->priceFor($interval)->multipliedBy($subscription->quantity)->prorate($remaining, $total);

        return [
            'credit' => $credit,
            'charge' => $charge,
            'due' => $charge->minus($credit)->atLeastZero(),
            'prorated' => true,
        ];
    }
}
