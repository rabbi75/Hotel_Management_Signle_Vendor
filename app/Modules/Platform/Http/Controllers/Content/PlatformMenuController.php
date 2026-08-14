<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\LinkTarget;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Http\Resources\MenuResource;
use App\Modules\CMS\Models\Menu;
use App\Modules\CMS\Models\Page;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Requests\Content\StorePlatformMenuRequest;
use App\Modules\Platform\Http\Requests\Content\UpdatePlatformMenuRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformMenuController extends Controller
{
    use ManagesPlatformContent;

    public function index(Request $request): Response
    {
        $this->authorizeContentOperator($request);

        $menus = $this->platformOwned(Menu::class)
            ->with(['items.page'])
            ->orderBy('location')
            ->get();

        return Inertia::render('cms/menus/index', [
            'panel' => 'admin',
            'menus' => MenuResource::collection($menus)->resolve($request),
            'locations' => MenuLocation::options(),
            'targets' => LinkTarget::options(),
            'pages' => $this->pageOptions(),
            'can' => ['manage' => true],
        ]);
    }

    public function store(StorePlatformMenuRequest $request): RedirectResponse
    {
        $menu = new Menu([
            'name' => (string) $request->string('name'),
            'location' => MenuLocation::from((string) $request->string('location')),
        ]);
        $menu->company_id = null;
        $menu->save();

        return back()->with('success', __('Menu created.'));
    }

    public function update(UpdatePlatformMenuRequest $request, Menu $menu): RedirectResponse
    {
        $this->ensurePlatformOwned($menu);

        if ($request->has('name')) {
            $menu->name = (string) $request->string('name');
        }

        if ($request->has('location')) {
            $menu->location = MenuLocation::from((string) $request->string('location'));
        }

        $menu->save();

        return back()->with('success', __('Menu updated.'));
    }

    public function destroy(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorizeContentOperator($request);
        $this->ensurePlatformOwned($menu);

        $menu->delete();

        return back()->with('success', __('Menu deleted.'));
    }

    /**
     * @return array<array-key, string>
     */
    protected function pageOptions(): array
    {
        $options = [];

        foreach ($this->platformOwned(Page::class)->orderBy('title')->get(['id', 'title']) as $page) {
            $options[(string) $page->id] = $page->title;
        }

        return $options;
    }
}
