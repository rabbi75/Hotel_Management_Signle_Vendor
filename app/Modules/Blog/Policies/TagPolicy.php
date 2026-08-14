<?php

declare(strict_types=1);

namespace App\Modules\Blog\Policies;

use App\Modules\Blog\Models\Tag;
use App\Modules\User\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['blog.posts.view', 'blog.taxonomy.manage']);
    }

    public function view(User $user, Tag $tag): bool
    {
        return $this->inCurrentWorkspace($tag) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('blog.taxonomy.manage');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $this->inCurrentWorkspace($tag) && $user->can('blog.taxonomy.manage');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $this->update($user, $tag);
    }

    protected function inCurrentWorkspace(Tag $tag): bool
    {
        return $tag->company_id === current_company_id();
    }
}
