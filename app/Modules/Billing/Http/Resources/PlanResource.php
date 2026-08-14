<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Plan $plan */
        $plan = $this->resource;

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'features' => $plan->features,
            'entitlements' => $plan->entitlements,
            'limits' => $plan->limits,

            // Minor units for arithmetic, formatted strings for display. The
            // client never divides by 100 itself.
            'monthly_price' => $plan->monthly_price,
            'yearly_price' => $plan->yearly_price,
            'monthly_price_formatted' => $plan->priceFor(BillingInterval::Monthly)->format(),
            'yearly_price_formatted' => $plan->priceFor(BillingInterval::Yearly)->format(),
            'yearly_savings' => $plan->yearlySavings()->amount,
            'yearly_savings_formatted' => $plan->yearlySavings()->format(),
            'currency' => $plan->currency,
            'trial_days' => $plan->trial_days,
            'is_active' => $plan->is_active,
            'is_public' => $plan->is_public,
            'is_free' => $plan->isFree(),
            'sort' => $plan->sort,
            'gateway_prices' => $plan->gateway_prices ?? [],
            'subscribers_count' => $this->counter($plan, 'subscriptions_count'),
            'created_at' => $plan->created_at?->toIso8601String(),
        ];
    }

    protected function counter(Plan $plan, string $key): ?int
    {
        $value = $plan->getAttributes()[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
