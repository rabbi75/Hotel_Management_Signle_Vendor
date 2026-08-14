<?php

declare(strict_types=1);

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\DTOs\PostData;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Services\PostRenderer;
use Illuminate\Support\Facades\DB;

class CreatePost
{
    public function __construct(protected PostRenderer $renderer) {}

    public function handle(PostData $data, ?int $authorId = null): Post
    {
        return DB::transaction(function () use ($data, $authorId): Post {
            $post = new Post;
            $post->fill($data->toAttributes());
            $post->author_id = $authorId;

            // "Publish now" with no date given still needs one, or the post
            // would satisfy the status filter and fail the date filter forever.
            if ($data->status === PostStatus::Published && $post->published_at === null) {
                $post->published_at = now()->toImmutable();
            }

            $this->renderer->apply($post);
            $post->save();

            if ($data->tagIds !== null) {
                $post->tags()->sync($data->tagIds);
            }

            $post->saveSeo($data->seo);

            return $post;
        });
    }
}
