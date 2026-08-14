<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Resources;

use App\Modules\Media\Models\MediaFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MediaFolder
 */
class MediaFolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MediaFolder $folder */
        $folder = $this->resource;

        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'slug' => $folder->slug,
            'path' => $folder->path,
            'parent_id' => $folder->parent_id,
            'depth' => count($folder->segments()) - 1,
            'assets_count' => $this->counter($folder, 'assets_count'),
            'children_count' => $this->counter($folder, 'children_count'),
            'children' => $folder->relationLoaded('children')
                ? self::collection($folder->children)->resolve($request)
                : [],
            'created_at' => $folder->created_at?->toIso8601String(),
        ];
    }

    protected function counter(MediaFolder $folder, string $key): ?int
    {
        $value = $folder->getAttributes()[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
