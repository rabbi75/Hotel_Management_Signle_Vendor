<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Resources;

use App\Modules\CMS\Models\Menu;
use App\Modules\CMS\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Menu
 */
class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Menu $menu */
        $menu = $this->resource;

        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'location' => $menu->location->value,
            'location_label' => $menu->location->label(),
            'items' => $this->tree($request, $menu),
            'created_at' => $menu->created_at?->toIso8601String(),
        ];
    }

    /**
     * The flat item list rebuilt into a tree. Building it here rather than with
     * nested eager loads keeps the depth unbounded without an N+1.
     *
     * @return list<array<string, mixed>>
     */
    protected function tree(Request $request, Menu $menu): array
    {
        if (! $menu->relationLoaded('items')) {
            return [];
        }

        /** @var array<int, list<array<string, mixed>>> $byParent */
        $byParent = [];

        foreach ($menu->items as $item) {
            /** @var MenuItem $item */
            $byParent[$item->parent_id ?? 0][] = (new MenuItemResource($item))->resolve($request);
        }

        return $this->attach($byParent, 0);
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $byParent
     * @return list<array<string, mixed>>
     */
    protected function attach(array $byParent, int $parentId): array
    {
        $nodes = $byParent[$parentId] ?? [];

        return array_map(
            function (array $node) use ($byParent): array {
                $id = is_numeric($node['id'] ?? null) ? (int) $node['id'] : 0;

                return [...$node, 'children' => $this->attach($byParent, $id)];
            },
            $nodes,
        );
    }
}
