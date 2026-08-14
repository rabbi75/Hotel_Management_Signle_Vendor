<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Resources;

use App\Modules\CMS\Models\Page;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Page $page */
        $page = $this->resource;

        $author = $page->relationLoaded('author') ? $page->author : null;

        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'path' => $page->path(),
            'status' => $page->status->value,
            'status_label' => $page->status->label(),
            'status_color' => $page->status->color(),
            'layout' => $page->layout,
            'parent_id' => $page->parent_id,
            'parent' => $page->relationLoaded('parent') ? $page->parent?->title : null,
            'seo' => $page->seo ?? [],
            'is_homepage' => $page->is_homepage,
            'is_public' => $page->isPubliclyVisible(),
            'author' => $author instanceof User ? $author->name : null,
            'blocks_count' => $this->counter($page, 'blocks_count'),
            'blocks' => $page->relationLoaded('blocks')
                ? PageBlockResource::collection($page->blocks)->resolve($request)
                : [],
            'published_at' => $page->published_at?->toIso8601String(),
            'created_at' => $page->created_at?->toIso8601String(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }

    protected function counter(Page $page, string $key): ?int
    {
        $value = $page->getAttributes()[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
