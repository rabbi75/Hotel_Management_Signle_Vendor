<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Blog\Models\Category;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Every nullable column is stated explicitly: Model::shouldBeStrict() turns
     * a missing attribute into an exception rather than a silent null.
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }
}
