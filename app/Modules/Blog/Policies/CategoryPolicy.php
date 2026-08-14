<?php

declare(strict_types=1);

namespace App\Modules\Blog\Policies;

use App\Modules\Blog\Models\Category;
use App\Modules\User\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['blog.posts.view', 'blog.taxonomy.manage']);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->inCurrentWorkspace($category) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('blog.taxonomy.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $this->inCurrentWorkspace($category) && $user->can('blog.taxonomy.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->update($user, $category);
    }

    protected function inCurrentWorkspace(Category $category): bool
    {
        return $category->company_id === current_company_id();
    }
}
