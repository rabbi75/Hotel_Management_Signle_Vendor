<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\PageRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Draft preview.
 *
 * Two independent gates guard it: the signature proves the link came from this
 * application and has not expired, and the policy proves the person following
 * it is allowed to read drafts. A leaked link is therefore still useless to an
 * outsider.
 */
class PagePreviewController extends Controller
{
    public function __construct(protected PageRenderer $renderer) {}

    /**
     * Mint a fresh preview link for the editor's "preview" button.
     */
    public function store(Page $page): JsonResponse
    {
        Gate::authorize('preview', $page);

        return response()->json(['url' => $this->urlFor($page), 'expires_in' => $this->ttl()]);
    }

    public function show(Page $page): Response
    {
        Gate::authorize('preview', $page);

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
            'cms.pages.preview',
            now()->addSeconds($this->ttl()),
            ['page' => $page->id],
        );
    }

    protected function ttl(): int
    {
        return max(60, (int) config('saas.cms.preview_ttl'));
    }
}
