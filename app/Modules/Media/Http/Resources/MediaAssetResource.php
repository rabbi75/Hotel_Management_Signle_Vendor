<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Resources;

use App\Modules\Media\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MediaAsset
 */
class MediaAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MediaAsset $asset */
        $asset = $this->resource;

        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'title' => $asset->title,
            'alt' => $asset->alt,
            'caption' => $asset->caption,
            'tags' => $asset->tags ?? [],
            'folder_id' => $asset->folder_id,
            'folder' => $asset->relationLoaded('folder') ? $asset->folder?->name : null,
            'type' => $asset->type()->value,
            'type_label' => $asset->type()->label(),
            'type_color' => $asset->type()->color(),
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'size' => $asset->size,
            'size_human' => $asset->humanSize(),
            'width' => $asset->width,
            'height' => $asset->height,
            'url' => $asset->url(),
            'thumb_url' => $asset->isImage() ? $asset->conversionUrl('thumb') : null,
            'preview_url' => $asset->isImage() ? $asset->conversionUrl('preview') : null,
            'uploaded_by' => $asset->uploaded_by,
            'uploader' => $asset->relationLoaded('uploader') ? $asset->uploader?->name : null,
            'original_asset_id' => $asset->original_asset_id,
            'versions_count' => $this->counter($asset, 'versions_count'),
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];
    }

    protected function counter(MediaAsset $asset, string $key): ?int
    {
        $value = $asset->getAttributes()[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
