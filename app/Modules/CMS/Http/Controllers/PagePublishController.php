<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\PageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PagePublishController extends Controller
{
    public function __construct(protected PageService $pages) {}

    public function store(Page $page): RedirectResponse
    {
        Gate::authorize('publish', $page);

        $this->pages->publish($page);

        return back()->with('success', __('Page published.'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        Gate::authorize('publish', $page);

        $this->pages->unpublish($page);

        return back()->with('success', __('Page unpublished.'));
    }

    public function schedule(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('publish', $page);

        $validated = $request->validate([
            'published_at' => ['required', 'date', 'after:now'],
        ]);

        $this->pages->schedule($page, (string) $validated['published_at']);

        return back()->with('success', __('Page scheduled.'));
    }
}
