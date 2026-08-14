<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\CMS\Concerns;

use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Models\PageBlock;
use App\Modules\Platform\Models\Admin;
use App\Support\Concerns\BelongsToCompany;

/**
 * The console's answer to the CMS form requests' `authorize()`.
 *
 * The tenant requests defer to `PagePolicy`, which is typed to a `User` and
 * checks the active workspace — neither of which exists in the console. Here the
 * question is instead: is this an operator who may manage platform pages, and is
 * the record actually platform-owned?
 */
trait AuthorizesPlatformPages
{
    protected function operatorMayManagePages(): bool
    {
        $admin = $this->user('admin');

        return $admin instanceof Admin && $admin->can('platform.pages.manage');
    }

    /**
     * A page with a workspace belongs to a tenant, and the console edits pages
     * through this route only — never a tenant's.
     *
     * `getAttribute()` rather than `->company_id`, matching
     * {@see BelongsToCompany}: static analysis reads the
     * column's nullability from the original migration and cannot see the later
     * `change()` that relaxed it, so the property access looks non-null.
     */
    protected function isPlatformOwned(mixed $page): bool
    {
        return $page instanceof Page && $page->getAttribute('company_id') === null;
    }

    protected function blockIsPlatformOwned(mixed $block): bool
    {
        return $block instanceof PageBlock && $block->getAttribute('company_id') === null;
    }
}
