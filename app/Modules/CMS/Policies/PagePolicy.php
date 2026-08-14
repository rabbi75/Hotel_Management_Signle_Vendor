<?php

declare(strict_types=1);

namespace App\Modules\CMS\Policies;

use App\Modules\CMS\Models\Page;
use App\Modules\User\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.pages.view');
    }

    public function view(User $user, Page $page): bool
    {
        return $this->inCurrentWorkspace($page) && $user->can('cms.pages.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cms.pages.create');
    }

    public function update(User $user, Page $page): bool
    {
        return $this->inCurrentWorkspace($page) && $user->can('cms.pages.update');
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->inCurrentWorkspace($page) && $user->can('cms.pages.delete');
    }

    public function duplicate(User $user, Page $page): bool
    {
        return $this->view($user, $page) && $user->can('cms.pages.create');
    }

    public function publish(User $user, Page $page): bool
    {
        return $this->inCurrentWorkspace($page) && $user->can('cms.pages.publish');
    }

    /**
     * Previewing an unpublished page is an editorial act: the signed URL proves
     * the link was issued by the application, and this proves the holder is
     * allowed to read drafts.
     */
    public function preview(User $user, Page $page): bool
    {
        return $this->view($user, $page);
    }

    /**
     * The global scope already constrains queries, but a model resolved by an
     * explicit id (an import, a job) must still be re-checked.
     */
    protected function inCurrentWorkspace(Page $page): bool
    {
        return $page->company_id === current_company_id();
    }
}
