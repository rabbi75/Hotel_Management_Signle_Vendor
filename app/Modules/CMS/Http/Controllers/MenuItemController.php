<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\LinkTarget;
use App\Modules\CMS\Http\Requests\ReorderMenuItemsRequest;
use App\Modules\CMS\Http\Requests\StoreMenuItemRequest;
use App\Modules\CMS\Http\Requests\UpdateMenuItemRequest;
use App\Modules\CMS\Models\Menu;
use App\Modules\CMS\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MenuItemController extends Controller
{
    public function store(StoreMenuItemRequest $request, Menu $menu): RedirectResponse
    {
        $parentId = $request->input('parent_id');
        $pageId = $request->input('page_id');

        $item = new MenuItem([
            'menu_id' => $menu->id,
            'parent_id' => is_numeric($parentId) ? (int) $parentId : null,
            'page_id' => is_numeric($pageId) ? (int) $pageId : null,
            'label' => (string) $request->string('label'),
            'url' => $request->string('url')->toString() ?: null,
            'target' => LinkTarget::tryFrom((string) $request->string('target')) ?? LinkTarget::Self,
            'icon' => $request->string('icon')->toString() ?: null,
            'permission' => $request->string('permission')->toString() ?: null,
            'order' => $request->integer('order', $this->nextOrder($menu)),
        ]);
        $item->company_id = $menu->company_id;
        $item->save();

        return back()->with('success', __('Menu item added.'));
    }

    /**
     * Only the submitted keys are written: the nested builder saves a single
     * field at a time, and writing the full model would clear everything the
     * inline form did not render.
     */
    public function update(UpdateMenuItemRequest $request, MenuItem $item): RedirectResponse
    {
        if ($request->has('label')) {
            $item->label = (string) $request->string('label');
        }

        foreach (['url', 'icon', 'permission'] as $field) {
            if ($request->has($field)) {
                $item->setAttribute($field, $request->string($field)->toString() ?: null);
            }
        }

        foreach (['parent_id', 'page_id', 'order'] as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $item->setAttribute($field, is_numeric($value) ? (int) $value : null);
            }
        }

        if ($request->has('target')) {
            $item->target = LinkTarget::tryFrom((string) $request->string('target')) ?? LinkTarget::Self;
        }

        $item->save();

        return back();
    }

    public function reorder(ReorderMenuItemsRequest $request, Menu $menu): RedirectResponse
    {
        Gate::authorize('update', $menu);

        DB::transaction(function () use ($request): void {
            foreach ($request->tree() as $node) {
                MenuItem::query()
                    ->whereKey($node['id'])
                    ->update(['parent_id' => $node['parent_id'], 'order' => $node['order']]);
            }
        });

        return back();
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->menu()->firstOrFail());

        $item->delete();

        return back()->with('success', __('Menu item removed.'));
    }

    protected function nextOrder(Menu $menu): int
    {
        $max = $menu->items()->max('order');

        return is_numeric($max) ? ((int) $max) + 1 : 0;
    }
}
