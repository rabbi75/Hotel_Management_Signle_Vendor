<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Resources;

use App\Modules\Blog\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Comment $comment */
        $comment = $this->resource;

        return [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'post' => $comment->relationLoaded('post') ? $comment->post?->title : null,
            'parent_id' => $comment->parent_id,
            'author' => $comment->authorName(),
            'author_email' => $comment->user_id === null ? $comment->guest_email : $comment->user?->email,
            'is_guest' => $comment->user_id === null,
            // Plain text. Comments have no markup path at all, so this value is
            // rendered as a string on the client and never as HTML.
            'body' => $comment->body,
            'status' => $comment->status->value,
            'status_label' => $comment->status->label(),
            'status_color' => $comment->status->color(),
            'ip_address' => $comment->ip_address,
            'replies' => $comment->relationLoaded('replies')
                ? self::collection($comment->replies)->resolve($request)
                : [],
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }
}
