<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Subscription $subscription */
        $subscription = $this->resource;

        return [
            'id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'plan' => $subscription->relationLoaded('plan')
                ? (new PlanResource($subscription->plan))->resolve($request)
                : null,
            'gateway' => $subscription->gateway,
            'status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'status_color' => $subscription->status->color(),
            'interval' => $subscription->interval->value,
            'interval_label' => $subscription->interval->label(),
            'quantity' => $subscription->quantity,
            'price' => $subscription->relationLoaded('plan') ? $subscription->price()->amount : null,
            'price_formatted' => $subscription->relationLoaded('plan') ? $subscription->price()->format() : null,
            'on_trial' => $subscription->onTrial(),
            'is_cancelling' => $subscription->isCancelling(),
            'grants_access' => $subscription->grantsAccess(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'current_period_start' => $subscription->current_period_start?->toIso8601String(),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'cancels_at' => $subscription->cancels_at?->toIso8601String(),
            'ended_at' => $subscription->ended_at?->toIso8601String(),
            'created_at' => $subscription->created_at?->toIso8601String(),
        ];
    }
}
