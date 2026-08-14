<?php

declare(strict_types=1);

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\DTOs\PostData;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Services\PostRenderer;
use App\Modules\SEO\DTOs\SeoMetaData;
use Illuminate\Support\Facades\DB;

class UpdatePost
{
    public function __construct(protected PostRenderer $renderer) {}

    public function handle(Post $post, PostData $data): Post
    {
        return DB::transaction(function () use ($post, $data): Post {
            $post->fill($data->toUpdateAttributes());

            if ($data->status === PostStatus::Published && $post->published_at === null) {
                $post->published_at = now()->toImmutable();
            }

            // The cached render is only rebuilt when the source it derives from
            // actually moved; recomputing it on a metadata-only save would burn
            // a markdown parse on every autosave keystroke.
            if ($post->isDirty(['body', 'body_format', 'excerpt'])) {
                $this->renderer->apply($post);
            }

            $post->save();

            if ($data->tagIds !== null) {
                $post->tags()->sync($data->tagIds);
            }

            // Absent SEO payload leaves the stored meta alone; the panel always
            // submits the whole object when it is open.
            if ($data->seo instanceof SeoMetaData) {
                $post->saveSeo($data->seo);
            }

            return $post;
        });
    }
}
