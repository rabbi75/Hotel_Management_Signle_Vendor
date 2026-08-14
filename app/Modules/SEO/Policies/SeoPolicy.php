<?php

declare(strict_types=1);

namespace App\Modules\SEO\Policies;

use App\Modules\SEO\Models\SeoMeta;
use App\Modules\User\Models\User;

class SeoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('seo.view');
    }

    public function view(User $user, SeoMeta $meta): bool
    {
        return $this->inCurrentWorkspace($meta) && $user->can('seo.view');
    }

    public function create(User $user): bool
    {
        return $user->can('seo.update');
    }

    public function update(User $user, SeoMeta $meta): bool
    {
        return $this->inCurrentWorkspace($meta) && $user->can('seo.update');
    }

    public function delete(User $user, SeoMeta $meta): bool
    {
        return $this->update($user, $meta);
    }

    public function generateSitemap(User $user): bool
    {
        return $user->can('seo.sitemap.generate');
    }

    protected function inCurrentWorkspace(SeoMeta $meta): bool
    {
        return $meta->company_id === current_company_id();
    }
}
