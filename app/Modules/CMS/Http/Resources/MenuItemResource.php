<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Resources;

use App\Modules\CMS\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MenuItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'menu_id' => $item->menu_id,
            'parent_id' => $item->parent_id,
            'page_id' => $item->page_id,
            'label' => $item->label,
            'url' => $item->url,
            'resolved_url' => $item->resolvedUrl(),
            'target' => $item->target->value,
            'icon' => $item->icon,
            'permission' => $item->permission,
            'order' => $item->order,
            'children' => [],
        ];
    }
}
