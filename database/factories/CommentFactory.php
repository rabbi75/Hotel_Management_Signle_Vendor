<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Blog\Enums\CommentStatus;
use App\Modules\Blog\Models\Comment;
use App\Modules\Blog\Models\Post;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'post_id' => Post::factory(),
            'parent_id' => null,
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'body' => fake()->paragraph(),
            'status' => CommentStatus::Pending,
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }

    public function forPost(Post $post): static
    {
        return $this->state(fn (array $attributes): array => [
            'post_id' => $post->id,
            'company_id' => $post->company_id,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => CommentStatus::Approved]);
    }

    public function spam(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => CommentStatus::Spam]);
    }
}
