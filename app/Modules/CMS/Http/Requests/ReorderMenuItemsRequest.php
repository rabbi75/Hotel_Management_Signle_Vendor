<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Requests;

use App\Modules\CMS\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReorderMenuItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $menu = $this->route('menu');

        return $menu instanceof Menu && Gate::allows('update', $menu);
    }

    /**
     * The whole tree arrives flattened: each entry carries its id, its new
     * parent and its position, which is what a nested drag-and-drop produces.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $menu = $this->route('menu');
        $menuId = $menu instanceof Menu ? $menu->id : 0;

        return [
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', Rule::exists('menu_items', 'id')->where('menu_id', $menuId)],
            'items.*.parent_id' => ['nullable', 'integer', Rule::exists('menu_items', 'id')->where('menu_id', $menuId)],
            'items.*.order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<array{id: int, parent_id: int|null, order: int}>
     */
    public function tree(): array
    {
        /** @var array<int, mixed> $items */
        $items = (array) $this->input('items', []);

        $tree = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parentId = $item['parent_id'] ?? null;

            $tree[] = [
                'id' => (int) ($item['id'] ?? 0),
                'parent_id' => is_numeric($parentId) ? (int) $parentId : null,
                'order' => (int) ($item['order'] ?? 0),
            ];
        }

        return $tree;
    }
}
