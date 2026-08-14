<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\CMS\Concerns;

use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Models\PageBlock;
use App\Modules\Platform\Models\Admin;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The two guards every console CMS action needs.
 *
 * `CompanyScope` is a no-op in the console — that is the whole point of the
 * admin route group — so it constrains nothing here. Platform ownership has to
 * be asserted explicitly instead, on the query *and* on anything route-model
 * binding hands back, or a tenant's page id in the URL would resolve happily.
 */
trait ManagesPlatformPages
{
    protected function authorizeOperator(Request $request): void
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin && $admin->can('platform.pages.manage'), 403);
    }

    /**
     * @return Builder<Page>
     */
    protected function platformPages(): Builder
    {
        return Page::query()->withoutGlobalScope(CompanyScope::class)->whereNull('company_id');
    }

    /**
     * A 404 rather than a 403: from the console's point of view a tenant's page
     * is not a forbidden resource, it is simply not one of these.
     *
     * `getAttribute()` rather than `->company_id`, matching
     * {@see BelongsToCompany}: static analysis reads the
     * column's nullability from the original migration and cannot see the later
     * `change()` that relaxed it, so the property access looks non-null.
     */
    protected function ensurePlatformPage(Page $page): Page
    {
        abort_unless($page->getAttribute('company_id') === null, 404);

        return $page;
    }

    protected function ensurePlatformBlock(PageBlock $block): PageBlock
    {
        abort_unless($block->getAttribute('company_id') === null, 404);

        return $block;
    }
}
