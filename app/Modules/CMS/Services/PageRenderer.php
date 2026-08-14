<?php

declare(strict_types=1);

namespace App\Modules\CMS\Services;

use App\Modules\CMS\Blocks\BlockSchema;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Models\Menu;
use App\Modules\CMS\Models\MenuItem;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Models\PageBlock;
use App\Modules\Company\Models\Company;
use App\Modules\OnlineBooking\Services\PublicRoomCatalogService;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns a stored page into the payload the public React renderer consumes.
 *
 * Only visible blocks survive, in order, each one filtered through its schema
 * so the renderer receives exactly the fields the block declares.
 */
class PageRenderer
{
    public function __construct(
        protected BlockRegistry $blocks,
        protected CurrentCompany $tenant,
        protected PublicRoomCatalogService $catalog,
    ) {}

    /**
     * Resolve a published page by slug for a public visitor.
     *
     * The public site has no session and therefore no active workspace, so the
     * lookup walks three owners in order: the active workspace if there somehow
     * is one, then the platform-owned pages the operator console authors, then
     * the installation's first workspace for installs that predate them.
     * Multi-site hosting would replace this resolution step — nothing else
     * changes.
     */
    public function resolvePublished(string $slug): ?Page
    {
        $page = $this->firstPublished(static fn (Builder $query): Builder => $query->where('slug', $slug));

        return $page instanceof Page && $page->isPubliclyVisible() ? $page : null;
    }

    public function homepage(): ?Page
    {
        return $this->firstPublished(static fn (Builder $query): Builder => $query->where('is_homepage', true));
    }

    /**
     * @param  bool  $includeHidden  True in the editor's preview pane, where a
     *                               hidden block still has to be visible to its
     *                               author.
     * @return list<array<string, mixed>>
     */
    public function blocks(Page $page, bool $includeHidden = false): array
    {
        $blocks = $page->relationLoaded('blocks') ? $page->blocks : $page->blocks()->get();

        $rendered = [];

        foreach ($blocks as $block) {
            /** @var PageBlock $block */
            if (! $includeHidden && ! $block->is_visible) {
                continue;
            }

            $schema = $this->blocks->get($block->type);

            if ($schema === null) {
                continue;
            }

            $rendered[] = [
                'id' => $block->id,
                'type' => $block->type,
                'order' => $block->order,
                'is_visible' => $block->is_visible,
                'data' => $this->hydrateBlock($schema, $schema->sanitise($block->data)),
            ];
        }

        return $rendered;
    }

    /**
     * @return array<string, mixed>
     */
    public function render(Page $page, bool $includeHidden = false): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'layout' => $page->layout,
            'status' => $page->status->value,
            'seo' => $this->seo($page),
            'blocks' => $this->blocks($page, $includeHidden),
            'published_at' => $page->published_at?->toIso8601String(),
        ];
    }

    /**
     * SEO metadata, with the installation's defaults filled in for anything the
     * author left blank.
     *
     * @return array<string, mixed>
     */
    public function seo(Page $page): array
    {
        $seo = $page->seo ?? [];

        return [
            'title' => $this->stringOr($seo, 'title', $page->title),
            'description' => $this->stringOr($seo, 'description', ''),
            'keywords' => $this->stringOr($seo, 'keywords', ''),
            'og_image' => $this->stringOr($seo, 'og_image', (string) config('saas.seo.default_og_image', '')),
            'canonical' => $this->stringOr($seo, 'canonical', ''),
            'noindex' => (bool) ($seo['noindex'] ?? ! config('saas.seo.robots_indexable', false)),
        ];
    }

    /**
     * A location's menu, as a nested tree of links.
     *
     * @return list<array<string, mixed>>
     */
    public function menu(MenuLocation $location): array
    {
        // Platform-owned menus win over a tenant's, matching how pages resolve:
        // the public site is the platform's, not the first workspace's.
        $menu = Menu::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->whereNull('company_id')
            ->where('location', $location)
            ->with(['items.page', 'items.children.page'])
            ->first()
            ?? Menu::query()
                ->where('location', $location)
                ->with(['items.page', 'items.children.page'])
                ->first();

        if (! $menu instanceof Menu) {
            return [];
        }

        $items = $menu->items->whereNull('parent_id');

        return array_values(array_map(
            fn (MenuItem $item): array => $this->menuItem($item),
            $items->all(),
        ));
    }

    /**
     * The first published page matching a constraint, searching each owner in
     * turn: active workspace, then the platform, then the oldest workspace.
     *
     * @param  callable(Builder<Page>): Builder<Page>  $constrain
     */
    protected function firstPublished(callable $constrain): ?Page
    {
        $company = $this->tenant->get();

        if ($company instanceof Company) {
            $page = $this->tenant->scopeTo($company, static fn (): ?Page => $constrain(Page::query()->published())->first());

            if ($page instanceof Page) {
                return $page;
            }
        }

        $page = $constrain(Page::query()->withoutGlobalScope(CompanyScope::class)->whereNull('company_id')->published())->first();

        if ($page instanceof Page) {
            return $page;
        }

        $fallback = Company::query()->oldest('id')->first();

        if (! $fallback instanceof Company) {
            return null;
        }

        return $this->tenant->scopeTo($fallback, static fn (): ?Page => $constrain(Page::query()->published())->first());
    }

    /**
     * @return array<string, mixed>
     */
    protected function menuItem(MenuItem $item): array
    {
        $children = $item->relationLoaded('children') ? $item->children : collect();

        return [
            'id' => $item->id,
            'label' => $item->label,
            'url' => $item->resolvedUrl(),
            'target' => $item->target->value,
            'icon' => $item->icon,
            'permission' => $item->permission,
            'children' => array_values(array_map(
                fn (MenuItem $child): array => $this->menuItem($child),
                $children->all(),
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function hydrateBlock(BlockSchema $schema, array $data): array
    {
        if ($schema::type() !== 'rooms') {
            return $data;
        }

        $catalog = $this->catalog->forPublicProperty();

        if ($catalog === null) {
            return [
                ...$data,
                'rooms' => [],
                'booking_url' => '/book',
                'check_in_date' => null,
                'check_out_date' => null,
                'slug' => null,
                'currency' => null,
            ];
        }

        return [
            ...$data,
            'rooms' => $catalog['rooms'],
            'booking_url' => route('booking.show', $catalog['setting']->public_slug),
            'check_in_date' => $catalog['check_in']->toDateString(),
            'check_out_date' => $catalog['check_out']->toDateString(),
            'slug' => $catalog['setting']->public_slug,
            'currency' => $catalog['hotel']->currency,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     */
    protected function stringOr(array $source, string $key, string $fallback): string
    {
        $value = $source[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
