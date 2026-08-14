<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\PageService;
use App\Modules\Platform\Http\Controllers\CMS\Concerns\ManagesPlatformPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformPagePublishController extends Controller
{
    use ManagesPlatformPages;

    public function __construct(protected PageService $pages) {}

    public function store(Request $request, Page $page): RedirectResponse
    {
        $this->authorizeOperator($request);
        $this->ensurePlatformPage($page);

        $this->pages->publish($page);

        return back()->with('success', __('Page published.'));
    }

    public function destroy(Request $request, Page $page): RedirectResponse
    {
        $this->authorizeOperator($request);
        $this->ensurePlatformPage($page);

        $this->pages->unpublish($page);

        return back()->with('success', __('Page unpublished.'));
    }

    public function schedule(Request $request, Page $page): RedirectResponse
    {
        $this->authorizeOperator($request);
        $this->ensurePlatformPage($page);

        $validated = $request->validate([
            'published_at' => ['required', 'date', 'after:now'],
        ]);

        $this->pages->schedule($page, (string) $validated['published_at']);

        return back()->with('success', __('Page scheduled.'));
    }
}
