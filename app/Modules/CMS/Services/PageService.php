<?php

declare(strict_types=1);

namespace App\Modules\CMS\Services;

use App\Modules\CMS\Blocks\BlockSchema;
use App\Modules\CMS\DTOs\PageData;
use App\Modules\CMS\Enums\PageStatus;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Models\PageBlock;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create, update, duplicate and publish pages.
 *
 * Slugs are unique per workspace, and "no workspace" is itself an owner: the
 * platform-owned pages authored from the operator console all carry a null
 * `company_id` and form their own slug namespace. Every probe here therefore
 * filters on the owner explicitly rather than leaning on the tenant scope,
 * which is a no-op in the console.
 */
class PageService
{
    public function create(PageData $data, ?User $author = null, ?int $companyId = null): Page
    {
        return DB::transaction(function () use ($data, $author, $companyId): Page {
            $page = new Page($data->toAttributes());
            $page->company_id = $companyId ?? app(CurrentCompany::class)->id();
            $page->slug = $this->uniqueSlug($data->slug ?? $data->title, null, $page->company_id);
            $page->created_by = $author?->id;
            $page->save();

            $this->enforceSingleHomepage($page);

            return $page;
        });
    }

    public function update(Page $page, PageData $data): Page
    {
        return DB::transaction(function () use ($page, $data): Page {
            $attributes = $data->toUpdateAttributes();

            if ($data->wasProvided('slug')) {
                $attributes['slug'] = $this->uniqueSlug($data->slug ?? $data->title, $page->id, $page->company_id);
            }

            $page->fill($attributes);
            $page->save();

            $this->enforceSingleHomepage($page);

            return $page;
        });
    }

    /**
     * A copy of a page and every one of its blocks, as a fresh draft.
     */
    public function duplicate(Page $page, ?User $author = null): Page
    {
        return DB::transaction(function () use ($page, $author): Page {
            $copy = new Page([
                'parent_id' => $page->parent_id,
                'title' => $page->title.' '.__('(copy)'),
                'status' => PageStatus::Draft,
                'layout' => $page->layout,
                'seo' => $page->seo,

                // Never carried over: two homepages is not a state the site can
                // be in, and a duplicate is always the one that should lose.
                'is_homepage' => false,

                'published_at' => null,
            ]);
            $copy->company_id = $page->company_id;
            $copy->slug = $this->uniqueSlug($page->slug, null, $page->company_id);
            $copy->created_by = $author?->id;
            $copy->created_by_admin_id = $page->created_by_admin_id;
            $copy->save();

            foreach ($page->blocks()->get() as $block) {
                $clone = new PageBlock([
                    'page_id' => $copy->id,
                    'type' => $block->type,
                    'order' => $block->order,
                    'data' => $block->data,
                    'is_visible' => $block->is_visible,
                ]);
                $clone->company_id = $copy->company_id;
                $clone->save();
            }

            return $copy;
        });
    }

    /**
     * Add a block at an explicit position, shifting whatever follows.
     *
     * Appending was the only thing the editor could do while blocks were added
     * from a modal; dragging one out of the palette and dropping it between two
     * existing sections means an insert, and an insert that does not shift its
     * successors leaves two blocks claiming the same `order`.
     *
     * @param  array<string, mixed>  $data
     */
    public function insertBlock(Page $page, BlockSchema $schema, ?int $position = null, array $data = [], bool $isVisible = true): PageBlock
    {
        return DB::transaction(function () use ($page, $schema, $position, $data, $isVisible): PageBlock {
            $count = $this->blocksOf($page)->count();
            $index = $position === null ? $count : max(0, min($position, $count));

            $this->blocksOf($page)
                ->where('order', '>=', $index)
                ->increment('order');

            $block = new PageBlock([
                'page_id' => $page->id,
                'type' => $schema::type(),
                'order' => $index,
                'data' => $schema->sanitise([...$schema->defaults(), ...$data]),
                'is_visible' => $isVisible,
            ]);
            $block->company_id = $page->company_id;
            $block->save();

            return $block;
        });
    }

    /**
     * Rewrite a page's block order from an ordered list of ids.
     *
     * @param  list<int>  $ids
     */
    public function reorderBlocks(Page $page, array $ids): void
    {
        DB::transaction(function () use ($page, $ids): void {
            foreach ($ids as $position => $id) {
                $this->blocksOf($page)->whereKey($id)->update(['order' => $position]);
            }
        });
    }

    public function publish(Page $page): Page
    {
        $page->status = PageStatus::Published;
        $page->published_at = $page->published_at ?? now();
        $page->save();

        return $page;
    }

    public function unpublish(Page $page): Page
    {
        $page->status = PageStatus::Draft;
        $page->published_at = null;
        $page->save();

        return $page;
    }

    public function schedule(Page $page, string $publishAt): Page
    {
        $page->status = PageStatus::Scheduled;
        $page->published_at = CarbonImmutable::parse($publishAt);
        $page->save();

        return $page;
    }

    /**
     * Flip every scheduled page whose moment has arrived. Called by the
     * scheduler, and idempotent so a missed run simply catches up.
     */
    public function publishDue(): int
    {
        return Page::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('status', PageStatus::Scheduled)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->update(['status' => PageStatus::Published]);
    }

    public function uniqueSlug(string $source, ?int $ignoreId = null, ?int $companyId = null): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 1;

        while ($this->slugTaken($slug, $ignoreId, $companyId)) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }

    /**
     * Blocks of one page, with the tenant scope lifted so the console — which
     * has no active workspace — sees a platform-owned page's blocks too.
     *
     * @return Builder<PageBlock>
     */
    protected function blocksOf(Page $page): Builder
    {
        return PageBlock::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('page_id', $page->id);
    }

    protected function slugTaken(string $slug, ?int $ignoreId, ?int $companyId): bool
    {
        // `where('company_id', null)` compiles to `is null`, which is exactly the
        // filter a platform-owned page needs.
        $query = Page::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->withTrashed()
            ->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    /**
     * Exactly one page per owner may be the homepage — per workspace for a
     * tenant, and one across the platform-owned set for the public site.
     */
    protected function enforceSingleHomepage(Page $page): void
    {
        if (! $page->is_homepage) {
            return;
        }

        Page::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $page->company_id)
            ->whereKeyNot($page->id)
            ->where('is_homepage', true)
            ->update(['is_homepage' => false]);
    }
}
