<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\SubscriptionStartedNotification;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Start a workspace's first (or next) subscription.
 *
 * A workspace already holding a live subscription must go through
 * {@see SwapPlan} instead — two concurrent subscriptions would double-bill.
 */
class Subscribe
{
    public function __construct(
        protected GatewayManager $gateways,
        protected ApplyCoupon $applyCoupon,
        protected GenerateInvoice $generateInvoice,
        protected SecurityLogger $security,
    ) {}

    /**
     * @param  string|null  $gateway  Forces a specific gateway instead of the
     *                                configured default. The platform panel passes
     *                                `manual` so an operator can grant a plan
     *                                without touching a payment processor.
     */
    public function handle(
        Company $company,
        Plan $plan,
        BillingInterval $interval,
        ?Coupon $coupon = null,
        ?string $paymentMethodId = null,
        ?string $gateway = null,
    ): Subscription {
        if ($this->liveSubscription($company) instanceof Subscription) {
            throw new BillingException(__('This workspace already has an active subscription.'));
        }

        if (! $plan->is_active) {
            throw new BillingException(__('That plan is not available.'));
        }

        $gateway = $this->gateways->driver($gateway);
        $remote = $gateway->subscribe($company, $plan, $interval, $paymentMethodId);

        $subscription = DB::transaction(function () use ($company, $plan, $remote, $coupon, $interval): Subscription {
            $subscription = new Subscription([
                ...$remote->toAttributes(),
                'plan_id' => $plan->id,
                'quantity' => 1,
                'dunning_attempts' => 0,
                'past_due_since' => null,
            ]);

            $subscription->company_id = $company->id;
            $subscription->save();

            if ($coupon instanceof Coupon) {
                $this->applyCoupon->handle($coupon, $company, $subscription, $plan, $interval);
            }

            // A trial is invoiced when it converts, not when it starts, and a
            // free plan is never invoiced at all.
            if ($subscription->status !== SubscriptionStatus::Trialing && ! $plan->isFree()) {
                $this->generateInvoice->handle($subscription, $coupon, InvoiceStatus::Open);
            }

            return $subscription;
        });

        $this->security->log(
            SecurityEvent::SubscriptionStarted,
            Auth::user(),
            __('Subscribed to :plan.', ['plan' => $plan->name]),
            ['company_id' => $company->id, 'plan_id' => $plan->id, 'interval' => $interval->value],
        );

        $company->owner->notify(new SubscriptionStartedNotification($subscription->load('plan')));

        return $subscription;
    }

    /**
     * The workspace's current subscription, if it has one.
     *
     * Queried without the tenant scope and pinned by id instead: this action is
     * also reachable from the console, where no workspace is resolved.
     */
    public function liveSubscription(Company $company): ?Subscription
    {
        return Subscription::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->live()
            ->latest('id')
            ->first();
    }
}
