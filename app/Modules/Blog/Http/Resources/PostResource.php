<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Resources;

use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\Tag;
use App\Modules\SEO\Http\Resources\SeoMetaResource;
use App\Modules\SEO\Models\SeoMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'body_html' => $post->body_html,
            'body_format' => $post->body_format->value,
            'status' => $post->status->value,
            'status_label' => $post->status->label(),
            'status_color' => $post->status->color(),
            'published_at' => $post->published_at?->toIso8601String(),
            'is_published' => $post->isPublished(),
            'featured_image' => $post->featured_image,
            'reading_time' => $post->reading_time,
            'view_count' => $post->view_count,
            'is_featured' => $post->is_featured,
            'allow_comments' => $post->allow_comments,
            'url' => $post->url(),

            'author_id' => $post->author_id,
            'author' => $post->relationLoaded('author') ? $post->author?->name : null,

            'category_id' => $post->category_id,
            'category' => $post->relationLoaded('category') ? $post->category?->name : null,

            'tag_ids' => $post->relationLoaded('tags')
                ? $post->tags->map(static fn (Tag $tag): int => $tag->id)->values()->all()
                : [],
            'tags' => $post->relationLoaded('tags')
                ? $post->tags->map(static fn (Tag $tag): string => $tag->name)->values()->all()
                : [],

            'comments_count' => $this->counter($post, 'comments_count'),
            'seo' => $this->seo($post, $request),

            'created_at' => $post->created_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The stored SEO override, or the all-null shape when the panel has never
     * been used on this post. Only sent when the relation was eager loaded, so
     * a list of fifty rows does not trigger fifty extra queries.
     *
     * @return array<string, mixed>|null
     */
    protected function seo(Post $post, Request $request): ?array
    {
        if (! $post->relationLoaded('seo')) {
            return null;
        }

        $meta = $post->getRelation('seo');

        return $meta instanceof SeoMeta
            ? (new SeoMetaResource($meta))->resolve($request)
            : SeoMetaResource::empty();
    }

    protected function counter(Post $post, string $key): ?int
    {
        $value = $post->getAttributes()[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
