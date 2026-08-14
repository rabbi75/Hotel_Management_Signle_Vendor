<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Company\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $start = CarbonImmutable::now()->subDays(5);

        return [
            'company_id' => Company::factory(),
            'plan_id' => Plan::factory(),
            'gateway' => 'manual',
            'gateway_id' => 'manual_sub_'.Str::lower(Str::random(16)),
            'status' => SubscriptionStatus::Active,
            'interval' => BillingInterval::Monthly,
            'quantity' => 1,
            'current_period_start' => $start,
            'current_period_end' => $start->addMonthNoOverflow(),
            'dunning_attempts' => 0,

            // Nullable columns are written explicitly so strict mode never sees
            // an attribute the insert skipped.
            'trial_ends_at' => null,
            'cancels_at' => null,
            'ended_at' => null,
            'past_due_since' => null,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }

    public function onTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => CarbonImmutable::now()->addDays($days),
        ]);
    }

    public function cancelling(): static
    {
        return $this->state(fn (array $attributes): array => [
            'cancels_at' => CarbonImmutable::now()->addDays(10),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Canceled,
            'cancels_at' => CarbonImmutable::now()->subDay(),
            'ended_at' => CarbonImmutable::now()->subDay(),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::PastDue,
            'past_due_since' => CarbonImmutable::now()->subDays(5),
            'dunning_attempts' => 2,
        ]);
    }
}
