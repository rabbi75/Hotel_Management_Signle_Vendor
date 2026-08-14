<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;

/**
 * The remote state of a subscription, as the gateway reports it.
 *
 * Gateways describe; actions persist. Keeping the driver free of Eloquent is
 * what makes swapping one out a matter of writing a single class.
 */
readonly class GatewaySubscriptionData extends Data
{
    public function __construct(
        public string $gateway,
        public ?string $gatewayId,
        public SubscriptionStatus $status,
        public BillingInterval $interval,
        public ?CarbonImmutable $trialEndsAt = null,
        public ?CarbonImmutable $currentPeriodStart = null,
        public ?CarbonImmutable $currentPeriodEnd = null,
        public ?CarbonImmutable $cancelsAt = null,
        public ?CarbonImmutable $endedAt = null,
    ) {}

    /**
     * Column values for the kit's own subscriptions table. Nulls are written as
     * nulls: this object is the complete remote truth, so an absent value is a
     * cleared value.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'gateway' => $this->gateway,
            'gateway_id' => $this->gatewayId,
            'status' => $this->status,
            'interval' => $this->interval,
            'trial_ends_at' => $this->trialEndsAt,
            'current_period_start' => $this->currentPeriodStart,
            'current_period_end' => $this->currentPeriodEnd,
            'cancels_at' => $this->cancelsAt,
            'ended_at' => $this->endedAt,
        ];
    }
}
