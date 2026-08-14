<?php

declare(strict_types=1);

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * Copies a post into a fresh draft.
 *
 * The copy is deliberately never published and never keeps the original's
 * slug or publication date: two live URLs with the same content is the exact
 * duplicate-content problem the SEO module then complains about.
 */
class DuplicatePost
{
    public function handle(Post $post, ?int $authorId = null): Post
    {
        return DB::transaction(function () use ($post, $authorId): Post {
            $copy = $post->replicate([
                'slug',
                'status',
                'published_at',
                'view_count',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

            $copy->title = __(':title (copy)', ['title' => $post->title]);
            $copy->slug = '';
            $copy->status = PostStatus::Draft;
            $copy->published_at = null;
            $copy->view_count = 0;
            $copy->is_featured = false;
            $copy->author_id = $authorId ?? $post->author_id;
            $copy->save();

            $copy->tags()->sync($post->tags()->allRelatedIds()->all());

            return $copy;
        });
    }
}
