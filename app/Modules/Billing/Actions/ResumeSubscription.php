<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\Subscription;
use Illuminate\Support\Facades\Auth;

class ResumeSubscription
{
    public function __construct(
        protected GatewayManager $gateways,
        protected SecurityLogger $security,
    ) {}

    public function handle(Subscription $subscription): Subscription
    {
        if (! $subscription->isCancelling()) {
            throw new BillingException(__('This subscription is not scheduled to be cancelled.'));
        }

        $remote = $this->gateways->driver()->resume($subscription);

        $subscription->fill($remote->toAttributes());
        $subscription->save();

        $this->security->log(
            SecurityEvent::SubscriptionResumed,
            Auth::user(),
            __('Subscription resumed.'),
            ['company_id' => $subscription->company_id, 'plan_id' => $subscription->plan_id],
        );

        return $subscription;
    }
}
