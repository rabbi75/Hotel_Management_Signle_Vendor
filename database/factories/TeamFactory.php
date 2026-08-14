<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'company_id' => Company::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'color' => fake()->hexColor(),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }
}
