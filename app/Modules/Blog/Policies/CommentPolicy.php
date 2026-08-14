<?php

declare(strict_types=1);

namespace App\Modules\Blog\Policies;

use App\Modules\Blog\Models\Comment;
use App\Modules\User\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('blog.comments.moderate');
    }

    public function moderate(User $user, Comment $comment): bool
    {
        return $this->inCurrentWorkspace($comment) && $user->can('blog.comments.moderate');
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->moderate($user, $comment);
    }

    protected function inCurrentWorkspace(Comment $comment): bool
    {
        return $comment->company_id === current_company_id();
    }
}
