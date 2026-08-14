<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\PageRenderer;
use App\Modules\Platform\Http\Controllers\CMS\Concerns\ManagesPlatformPages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Draft preview for the console, guarded the same way the tenant one is: the
 * signature proves the link came from this application and has not expired, and
 * the operator check proves whoever follows it may read drafts.
 */
class PlatformPagePreviewController extends Controller
{
    use ManagesPlatformPages;

    public function __construct(protected PageRenderer $renderer) {}

    public function store(Request $request, Page $page): JsonResponse
    {
        $this->authorizeOperator($request);
        $this->ensurePlatformPage($page);

        return response()->json(['url' => $this->urlFor($page), 'expires_in' => $this->ttl()]);
    }

    public function show(Request $request, Page $page): Response
    {
        $this->authorizeOperator($request);
        $this->ensurePlatformPage($page);

        return Inertia::render('cms/preview', [
            'page' => $this->renderer->render($page, includeHidden: true),
            'preview' => true,
            'expiresIn' => $this->ttl(),

            // The preview renders in the live site's shell, which needs the same
            // navigation the public page gets.
            'menus' => [
                'header' => $this->renderer->menu(MenuLocation::Header),
                'footer' => $this->renderer->menu(MenuLocation::Footer),
            ],
        ]);
    }

    protected function urlFor(Page $page): string
    {
        return URL::temporarySignedRoute(
            'admin.cms.pages.preview',
            now()->addSeconds($this->ttl()),
            ['page' => $page->id],
        );
    }

    protected function ttl(): int
    {
        return max(60, (int) config('saas.cms.preview_ttl'));
    }
}
