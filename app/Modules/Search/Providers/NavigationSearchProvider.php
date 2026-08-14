<?php

declare(strict_types=1);

namespace App\Modules\Search\Providers;

use App\Modules\Search\Contracts\SearchProvider;
use App\Modules\Search\DTOs\PageResult;
use App\Modules\User\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Pages, taken from the registered navigation.
 *
 * NavigationBuilder has already filtered its tree by the user's permissions, so
 * this provider needs no permission of its own: whatever it can see is by
 * definition something the user may reach.
 */
class NavigationSearchProvider implements SearchProvider
{
    public function __construct(protected NavigationBuilder $navigation) {}

    public function key(): string
    {
        return 'pages';
    }

    public function label(): string
    {
        return (string) __('Pages');
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function permission(): ?string
    {
        return null;
    }

    /**
     * @return Collection<int, PageResult>
     */
    public function search(string $term, int $limit): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return new Collection;
        }

        $needle = Str::lower($term);
        $pages = [];

        foreach ($this->navigation->for($user) as $section) {
            $label = is_string($section['label'] ?? null) ? $section['label'] : '';
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];

            foreach ($this->flatten($items, $label) as $page) {
                if (str_contains(Str::lower($page->label), $needle)
                    || str_contains(Str::lower($page->section), $needle)) {
                    $pages[] = $page;
                }
            }
        }

        return new Collection(array_slice($pages, 0, $limit));
    }

    /**
     * @return array{id: string, title: string, subtitle: string|null, icon: string|null, url: string|null, group: string}
     */
    public function toResult(mixed $model): array
    {
        /** @var PageResult $model */
        return [
            'id' => 'page-'.Str::slug($model->section.'-'.$model->label),
            'title' => $model->label,
            'subtitle' => $model->section === '' ? null : $model->section,
            'icon' => $model->icon ?? $this->icon(),
            'url' => $model->href,
            'group' => $this->key(),
        ];
    }

    /**
     * Flattens the nav tree; a parent with children is itself linkable only if
     * it resolved to a URL.
     *
     * @param  array<int, mixed>  $items
     * @return list<PageResult>
     */
    protected function flatten(array $items, string $section): array
    {
        $pages = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $href = $item['href'] ?? null;
            $label = $item['label'] ?? null;

            if (is_string($href) && is_string($label)) {
                $pages[] = new PageResult(
                    label: $label,
                    href: $href,
                    icon: is_string($item['icon'] ?? null) ? $item['icon'] : null,
                    section: $section,
                );
            }

            if (is_array($item['children'] ?? null)) {
                $pages = [...$pages, ...$this->flatten($item['children'], $section)];
            }
        }

        return $pages;
    }
}
