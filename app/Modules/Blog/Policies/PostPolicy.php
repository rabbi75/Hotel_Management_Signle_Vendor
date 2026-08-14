<?php

declare(strict_types=1);

namespace App\Modules\Blog\Policies;

use App\Modules\Blog\Models\Post;
use App\Modules\User\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('blog.posts.view');
    }

    public function view(User $user, Post $post): bool
    {
        return $this->inCurrentWorkspace($post) && $user->can('blog.posts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('blog.posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $this->inCurrentWorkspace($post) && $user->can('blog.posts.update');
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->inCurrentWorkspace($post) && $user->can('blog.posts.delete');
    }

    public function publish(User $user, Post $post): bool
    {
        return $this->inCurrentWorkspace($post) && $user->can('blog.posts.publish');
    }

    /**
     * Duplicating writes a new row, so it needs create rights as well as the
     * right to read the original.
     */
    public function duplicate(User $user, Post $post): bool
    {
        return $this->view($user, $post) && $user->can('blog.posts.create');
    }

    /**
     * The global scope already constrains queries, but a model resolved by an
     * explicit id (an import, a job) must still be re-checked.
     */
    protected function inCurrentWorkspace(Post $post): bool
    {
        return $post->company_id === current_company_id();
    }
}
