<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\LinkTarget;
use App\Modules\CMS\Models\Menu;
use App\Modules\CMS\Models\MenuItem;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Requests\Content\ReorderPlatformMenuItemsRequest;
use App\Modules\Platform\Http\Requests\Content\StorePlatformMenuItemRequest;
use App\Modules\Platform\Http\Requests\Content\UpdatePlatformMenuItemRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformMenuItemController extends Controller
{
    use ManagesPlatformContent;

    public function store(StorePlatformMenuItemRequest $request, Menu $menu): RedirectResponse
    {
        $this->ensurePlatformOwned($menu);

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
        $item->company_id = null;
        $item->save();

        return back()->with('success', __('Menu item added.'));
    }

    public function update(UpdatePlatformMenuItemRequest $request, MenuItem $item): RedirectResponse
    {
        $this->ensurePlatformOwned($item);

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

    public function reorder(ReorderPlatformMenuItemsRequest $request, Menu $menu): RedirectResponse
    {
        $this->ensurePlatformOwned($menu);

        DB::transaction(function () use ($request): void {
            foreach ($request->tree() as $node) {
                MenuItem::query()
                    ->withoutGlobalScopes()
                    ->whereKey($node['id'])
                    ->whereNull('company_id')
                    ->update(['parent_id' => $node['parent_id'], 'order' => $node['order']]);
            }
        });

        return back();
    }

    public function destroy(Request $request, MenuItem $item): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($item);

        $item->delete();

        return back()->with('success', __('Menu item removed.'));
    }

    protected function nextOrder(Menu $menu): int
    {
        $max = $menu->items()->withoutGlobalScopes()->whereNull('company_id')->max('order');

        return is_numeric($max) ? ((int) $max) + 1 : 0;
    }
}
