<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'features' => ['Unlimited projects', 'Priority support'],
            'entitlements' => ['chat', 'media'],
            'limits' => ['seats' => 10, 'storage_mb' => 5000, 'ai_credits' => 1000],
            'monthly_price' => 1900,
            'yearly_price' => 19000,
            'currency' => 'USD',
            'trial_days' => 0,
            'is_active' => true,
            'is_public' => true,
            'sort' => 0,

            // Set explicitly: strict mode throws on reading a column the insert
            // never touched, and PlanResource reads this on every render.
            'gateway_prices' => null,
            'deleted_at' => null,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes): array => [
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes): array => ['trial_days' => $days]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    /**
     * @param  array<string, int>  $limits
     */
    public function withLimits(array $limits): static
    {
        return $this->state(fn (array $attributes): array => ['limits' => $limits]);
    }
}
