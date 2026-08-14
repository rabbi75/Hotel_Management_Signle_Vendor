<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Controllers\Concerns;

use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\Tag;
use App\Modules\SEO\Services\SeoManager;
use App\Modules\User\Models\User;

/**
 * Picker options shared by the blog screens.
 *
 * All of these are workspace-scoped by the global tenant scope; none of them
 * takes a company id, because a caller that can pass one can pass the wrong one.
 */
trait ProvidesBlogOptions
{
    /**
     * Options are lists of value/label pairs rather than id-keyed maps: PHP
     * silently casts a numeric string key back to an int, so a map would arrive
     * on the client with number keys and break the string comparisons the
     * pickers do.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function categoryOptions(): array
    {
        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Category $category): array => [
                'value' => (string) $category->id,
                'label' => $category->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function tagOptions(): array
    {
        return Tag::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Tag $tag): array => [
                'value' => (string) $tag->id,
                'label' => $tag->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Only users who have actually written something — an author filter listing
     * every member of a 500-person workspace is not a filter.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function authorOptions(): array
    {
        $ids = Post::query()->whereNotNull('author_id')->distinct()->pluck('author_id');

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $user): array => [
                'value' => (string) $user->id,
                'label' => $user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * The limits and defaults the React SEO panel needs to render its counters
     * and its Google preview.
     *
     * @return array<string, mixed>
     */
    protected function seoContext(): array
    {
        $defaults = app(SeoManager::class)->defaults();

        return [
            'title_max' => $defaults['title_max'],
            'description_max' => $defaults['description_max'],
            'title_suffix' => $defaults['title_suffix'],
            'indexable' => $defaults['indexable'],
            'base_url' => rtrim((string) config('app.url'), '/'),
        ];
    }
}
