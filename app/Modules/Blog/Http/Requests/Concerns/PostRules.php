<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Requests\Concerns;

use App\Modules\Blog\Enums\BodyFormat;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

/**
 * Rules shared by the create and edit requests.
 *
 * Kept together because the two differ only in which post the uniqueness rule
 * must ignore, and duplicating twenty rules to express that is how the two
 * screens drift apart.
 *
 * @phpstan-require-extends FormRequest
 */
trait PostRules
{
    /**
     * @return array<string, mixed>
     */
    protected function postRules(?Post $post = null): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:200',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(Post::RESERVED_SLUGS),
                Rule::unique('blog_posts', 'slug')
                    ->where('company_id', current_company_id())
                    ->ignore($post?->id),
            ],

            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'nullable', 'string', 'max:500000'],
            'body_format' => ['required', new Enum(BodyFormat::class)],
            'status' => ['required', new Enum(PostStatus::class)],

            // Required as soon as the author asks for a schedule, and only then.
            'published_at' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === PostStatus::Scheduled->value),
                'nullable',
                'date',
            ],

            'category_id' => ['sometimes', 'nullable', 'integer', $this->existsInWorkspace(Category::class)],
            'tag_ids' => ['sometimes', 'array', 'max:25'],
            'tag_ids.*' => ['integer', $this->existsInWorkspace(Tag::class)],

            'featured_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_featured' => ['boolean'],
            'allow_comments' => ['boolean'],

            ...$this->seoRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function seoRules(): array
    {
        return [
            'seo' => ['sometimes', 'nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:255'],
            'seo.description' => ['nullable', 'string', 'max:500'],
            'seo.keywords' => ['nullable', 'string', 'max:255'],
            'seo.canonical_url' => ['nullable', 'url', 'max:2048'],
            'seo.robots_index' => ['boolean'],
            'seo.robots_follow' => ['boolean'],
            'seo.og_title' => ['nullable', 'string', 'max:255'],
            'seo.og_description' => ['nullable', 'string', 'max:500'],
            'seo.og_image' => ['nullable', 'string', 'max:2048'],
            'seo.twitter_card' => ['nullable', 'string', 'in:summary,summary_large_image,app,player'],
            'seo.twitter_title' => ['nullable', 'string', 'max:255'],
            'seo.twitter_description' => ['nullable', 'string', 'max:500'],
            'seo.twitter_image' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function postMessages(): array
    {
        return [
            'slug.regex' => __('A slug may only contain lowercase letters, numbers and single hyphens.'),
            'slug.not_in' => __('That slug is reserved by the blog itself. Choose another.'),
            'slug.unique' => __('Another post in this workspace already uses that slug.'),
            'published_at.required' => __('A scheduled post needs a date and time to go live.'),
        ];
    }

    /**
     * A foreign key may only point at a row in the caller's own workspace;
     * without the company clause an id from another tenant would validate.
     *
     * @param  class-string<Model>  $model
     */
    protected function existsInWorkspace(string $model): Exists
    {
        return Rule::exists((new $model)->getTable(), 'id')
            ->where('company_id', current_company_id());
    }
}
