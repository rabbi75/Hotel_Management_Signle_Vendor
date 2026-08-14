<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleAppearance;
use App\Support\Settings\SettingsRepository;
use App\Support\Theme\SidebarPalette;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sidebar palette for both panels.
 *
 * Deliberately an operator screen and not a tenant one: the console and the
 * tenant app are two faces of the same product, and how they look is a decision
 * for whoever runs the installation. Values live in the system settings scope,
 * which {@see HandleAppearance} reads on every request.
 */
class PlatformAppearanceController extends Controller
{
    protected const ADMIN_KEY = 'panel_theme.admin_sidebar';

    protected const APP_KEY = 'panel_theme.app_sidebar';

    public function __construct(protected SettingsRepository $settings) {}

    public function index(Request $request): Response
    {
        $this->authorizeOperator($request);

        return Inertia::render('admin/appearance', [
            'palettes' => SidebarPalette::options(),
            'settings' => [
                'admin_sidebar_theme' => $this->current(self::ADMIN_KEY),
                'app_sidebar_theme' => $this->current(self::APP_KEY),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeOperator($request);

        $rule = ['required', 'string', 'in:'.implode(',', SidebarPalette::keys())];

        $validated = $request->validate([
            'admin_sidebar_theme' => $rule,
            'app_sidebar_theme' => $rule,
        ]);

        $this->settings->setMany([
            self::ADMIN_KEY => $validated['admin_sidebar_theme'],
            self::APP_KEY => $validated['app_sidebar_theme'],
        ]);

        return back()->with('success', __('Appearance updated.'));
    }

    protected function current(string $key): string
    {
        $value = $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, $key, SidebarPalette::DEFAULT);

        return is_string($value) && SidebarPalette::has($value) ? $value : SidebarPalette::DEFAULT;
    }

    protected function authorizeOperator(Request $request): void
    {
        abort_if($request->user('admin')?->cannot('platform.appearance.manage') ?? true, 403);
    }
}
