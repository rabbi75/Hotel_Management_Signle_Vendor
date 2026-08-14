<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Resources;

use App\Modules\Blog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'parent' => $category->relationLoaded('parent') ? $category->parent?->name : null,
            'posts_count' => $this->counter($category, 'posts_count'),
            'children' => $category->relationLoaded('children')
                ? self::collection($category->children)->resolve($request)
                : [],
            'created_at' => $category->created_at?->toIso8601String(),
        ];
    }

    protected function counter(Category $category, string $key): ?int
    {
        $value = $category->getAttributes()[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
