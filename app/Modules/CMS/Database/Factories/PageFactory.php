<?php

declare(strict_types=1);

namespace App\Modules\CMS\Database\Factories;

use App\Modules\CMS\Enums\PageStatus;
use App\Modules\CMS\Models\Page;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'created_by' => null,
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'status' => PageStatus::Draft,
            'layout' => 'default',
            'seo' => null,
            'is_homepage' => false,
            'published_at' => null,
            'deleted_at' => null,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PageStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PageStatus::Scheduled,
            'published_at' => now()->addWeek(),
        ]);
    }
}
