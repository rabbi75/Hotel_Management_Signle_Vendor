<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Blog\Models\Tag;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word());

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }
}
