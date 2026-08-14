<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Billing\Enums\CouponType;
use App\Modules\Billing\Models\Coupon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'description' => fake()->sentence(4),
            'type' => CouponType::Percent,
            'value' => 20,
            'currency' => null,
            'max_redemptions' => null,
            'redeemed_count' => 0,
            'expires_at' => null,
            'plan_ids' => null,
            'is_active' => true,
        ];
    }

    public function fixed(int $minorUnits = 500, string $currency = 'USD'): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CouponType::Fixed,
            'value' => $minorUnits,
            'currency' => $currency,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => CarbonImmutable::now()->subDay(),
        ]);
    }

    public function limitedTo(int $redemptions): static
    {
        return $this->state(fn (array $attributes): array => ['max_redemptions' => $redemptions]);
    }
}
