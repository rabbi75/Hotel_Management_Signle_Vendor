<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\SubscriptionCancelledNotification;
use Illuminate\Support\Facades\Auth;

class CancelSubscription
{
    public function __construct(
        protected GatewayManager $gateways,
        protected SecurityLogger $security,
    ) {}

    /**
     * @param  bool  $immediately  Revoke access now instead of at the period end.
     */
    public function handle(Subscription $subscription, bool $immediately = false): Subscription
    {
        if (! $subscription->status->isLive()) {
            throw new BillingException(__('This subscription has already ended.'));
        }

        // The gateway that issued the subscription, not the configured default —
        // see the note in SwapPlan.
        $remote = $this->gateways->driver($subscription->gateway)->cancel($subscription, $immediately);

        $subscription->fill($remote->toAttributes());
        $subscription->save();

        $this->security->log(
            SecurityEvent::SubscriptionCancelled,
            Auth::user(),
            $immediately
                ? __('Subscription cancelled immediately.')
                : __('Subscription set to cancel at the end of the period.'),
            [
                'company_id' => $subscription->company_id,
                'plan_id' => $subscription->plan_id,
                'immediately' => $immediately,
            ],
        );

        $subscription->company->owner->notify(
            new SubscriptionCancelledNotification($subscription->load('plan')),
        );

        return $subscription;
    }
}
