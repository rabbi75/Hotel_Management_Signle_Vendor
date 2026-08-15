<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\LinkTarget;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Http\Requests\StoreMenuRequest;
use App\Modules\CMS\Http\Requests\UpdateMenuRequest;
use App\Modules\CMS\Http\Resources\MenuResource;
use App\Modules\CMS\Models\Menu;
use App\Modules\CMS\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Menu::class);

        $menus = Menu::query()->with(['items.page'])->orderByDesc('id')->get();

        return Inertia::render('cms/menus/index', [
            'menus' => MenuResource::collection($menus)->resolve($request),
            'locations' => MenuLocation::options(),
            'targets' => LinkTarget::options(),
            'pages' => $this->pageOptions(),
            'can' => [
                'manage' => Gate::allows('create', Menu::class),
            ],
        ]);
    }

    public function store(StoreMenuRequest $request): RedirectResponse
    {
        Menu::query()->create([
            'name' => (string) $request->string('name'),
            'location' => MenuLocation::from((string) $request->string('location')),
        ]);

        return back()->with('success', __('Menu created.'));
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        if ($request->has('name')) {
            $menu->name = (string) $request->string('name');
        }

        if ($request->has('location')) {
            $menu->location = MenuLocation::from((string) $request->string('location'));
        }

        $menu->save();

        return back()->with('success', __('Menu updated.'));
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        Gate::authorize('delete', $menu);

        $menu->delete();

        return back()->with('success', __('Menu deleted.'));
    }

    /**
     * @return array<array-key, string>
     */
    protected function pageOptions(): array
    {
        $options = [];

        foreach (Page::query()->orderBy('title')->get(['id', 'title']) as $page) {
            $options[(string) $page->id] = $page->title;
        }

        return $options;
    }
}
