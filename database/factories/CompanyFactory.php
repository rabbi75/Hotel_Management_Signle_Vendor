<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'uuid' => (string) Str::ulid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'owner_id' => User::factory(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'website' => fake()->optional()->url(),
            'city' => fake()->city(),
            'country_code' => fake()->countryCode(),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'locale' => 'en',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function onTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes): array => ['trial_ends_at' => now()->addDays($days)]);
    }
}
