<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\PageRenderer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The site root.
 *
 * Three outcomes, in order: a signed-in tenant user goes to their dashboard, a
 * visitor gets the CMS homepage if one is published, and an installation that
 * has not built a landing page yet still gets the starter kit's own welcome
 * screen. That last fallback is what keeps `git clone && migrate` working on an
 * empty database.
 *
 * The dashboard redirect reads the `web` guard specifically: an operator is
 * authenticated on the `admin` guard, and should see the page they are editing
 * rather than be bounced into a tenant app they have no workspace in.
 */
class HomeController extends Controller
{
    public function __construct(protected PageRenderer $renderer) {}

    public function __invoke(): Response|RedirectResponse
    {
        if (auth('web')->check()) {
            return redirect()->route('dashboard');
        }

        $page = $this->renderer->homepage();

        if (! $page instanceof Page) {
            return Inertia::render('welcome');
        }

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
