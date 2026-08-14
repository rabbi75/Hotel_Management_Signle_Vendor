<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Resources;

use App\Modules\Blog\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tag $tag */
        $tag = $this->resource;

        $count = $tag->getAttributes()['posts_count'] ?? null;

        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'posts_count' => is_numeric($count) ? (int) $count : null,
            'created_at' => $tag->created_at?->toIso8601String(),
        ];
    }
}
