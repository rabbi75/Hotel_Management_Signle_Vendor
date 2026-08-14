<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Blog\Enums\BodyFormat;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Post;
use App\Modules\Company\Models\Company;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * Every nullable column is stated explicitly: Model::shouldBeStrict() turns
     * a missing attribute into an exception rather than a silent null.
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->sentence(4));
        $body = fake()->paragraphs(3, true);

        return [
            'company_id' => Company::factory(),
            'author_id' => null,
            'category_id' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(12),
            'body' => $body,
            'body_html' => '<p>'.e($body).'</p>',
            'body_format' => BodyFormat::Markdown,
            'status' => PostStatus::Draft,
            'published_at' => null,
            'featured_image' => null,
            'reading_time' => 1,
            'view_count' => 0,
            'is_featured' => false,
            'allow_comments' => true,
            'seo' => null,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }

    public function published(?DateTimeInterface $at = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Published,
            'published_at' => $at ?? now()->subDay(),
        ]);
    }

    public function scheduled(?DateTimeInterface $at = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Scheduled,
            'published_at' => $at ?? now()->addDay(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => PostStatus::Archived]);
    }
}
