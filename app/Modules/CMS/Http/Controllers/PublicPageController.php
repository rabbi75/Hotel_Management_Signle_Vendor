<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\PageRenderer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public front end for CMS pages.
 *
 * Registered from a `booted` callback so it is the very last route in the
 * table: a catch-all declared any earlier would swallow /dashboard, /users and
 * every other application route.
 */
class PublicPageController extends Controller
{
    public function __construct(protected PageRenderer $renderer) {}

    public function __invoke(string $slug): Response
    {
        $page = $this->renderer->resolvePublished($slug);

        // A draft, a scheduled page, or a slug that was never claimed all look
        // identical from the outside: unpublished content must not be
        // discoverable by probing for a different status code.
        abort_unless($page instanceof Page, 404);

        return Inertia::render('cms/public-page', [
            'page' => $this->renderer->render($page),
            'preview' => false,
            'menus' => [
                'header' => $this->renderer->menu(MenuLocation::Header),
                'footer' => $this->renderer->menu(MenuLocation::Footer),
            ],
        ]);
    }
}
