<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Resources;

use App\Modules\CMS\Models\PageBlock;
use App\Modules\CMS\Services\BlockRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PageBlock
 */
class PageBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PageBlock $block */
        $block = $this->resource;

        $schema = app(BlockRegistry::class)->get($block->type);

        return [
            'id' => $block->id,
            'page_id' => $block->page_id,
            'type' => $block->type,
            'label' => $schema?->label() ?? $block->type,
            'icon' => $schema?->icon() ?? 'square',
            'order' => $block->order,
            'is_visible' => $block->is_visible,

            // Filtered through the schema, so a field removed from a block type
            // stops reaching the editor rather than lingering as a stale key.
            'data' => $schema?->sanitise($block->data) ?? $block->data,
        ];
    }
}
