<?php

declare(strict_types=1);

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\DTOs\CommentData;
use App\Modules\Blog\Enums\CommentStatus;
use App\Modules\Blog\Models\Comment;
use App\Modules\Blog\Models\Post;

class CreateComment
{
    public function handle(Post $post, CommentData $data): Comment
    {
        $comment = new Comment;

        $comment->fill([
            'post_id' => $post->id,
            'parent_id' => $this->resolveParent($post, $data->parentId),
            'user_id' => $data->userId,
            'guest_name' => $data->guestName === '' ? null : $data->guestName,
            'guest_email' => $data->guestEmail === '' ? null : $data->guestEmail,
            'body' => $data->body,
            // Never from the request: a client that picks its own status picks
            // `approved`.
            'status' => $this->initialStatus(),
            'ip_address' => $data->ipAddress,
        ]);

        $comment->company_id = $post->company_id;
        $comment->save();

        return $comment;
    }

    protected function initialStatus(): CommentStatus
    {
        return config('saas.blog.comments_require_approval', true)
            ? CommentStatus::Pending
            : CommentStatus::Approved;
    }

    /**
     * A reply may only attach to an approved comment on the same post —
     * otherwise a guessed id lets a stranger thread a reply under a pending or
     * spam comment, or under a comment on somebody else's post.
     */
    protected function resolveParent(Post $post, ?int $parentId): ?int
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Comment::query()
            ->whereKey($parentId)
            ->where('post_id', $post->id)
            ->where('status', CommentStatus::Approved)
            ->first();

        return $parent?->id;
    }
}
