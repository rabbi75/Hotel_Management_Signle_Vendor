<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content\Concerns;

use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Tag;
use App\Modules\SEO\Services\SeoManager;
use App\Support\Settings\SettingsRepository;

/**
 * Picker options for platform-owned blog content.
 */
trait ProvidesPlatformBlogOptions
{
    use ManagesPlatformContent;

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function categoryOptions(): array
    {
        return $this->platformOwned(Category::class)
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
        return $this->platformOwned(Tag::class)
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
     * Console posts are not attributed to tenant users.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function authorOptions(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function seoContext(): array
    {
        $settings = app(SettingsRepository::class);
        $defaults = app(SeoManager::class)->defaults();

        return [
            'title_max' => (int) ($settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.title_max') ?? $defaults['title_max']),
            'description_max' => (int) ($settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.description_max') ?? $defaults['description_max']),
            'title_suffix' => $defaults['title_suffix'],
            'indexable' => filter_var(
                $settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.robots_indexable', $defaults['indexable']),
                FILTER_VALIDATE_BOOL,
            ),
            'base_url' => rtrim((string) config('app.url'), '/'),
        ];
    }
}
