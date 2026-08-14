<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Branding\Branding;
use App\Support\Enums\Theme;
use App\Support\Settings\SettingsRepository;
use App\Support\Theme\SidebarPalette;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the colour scheme before the document is rendered.
 *
 * The value is shared with the root Blade view, which stamps the `dark` class
 * on <html> server-side. Without this the page would paint in the default theme
 * for one frame before React hydrated and corrected it. The sidebar palette is
 * resolved here for the same reason: shipping it in the initial HTML is what
 * keeps a themed rail from flashing the default colours on every load.
 */
class HandleAppearance
{
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = Theme::tryFrom((string) $request->cookie('appearance')) ?? Theme::System;

        View::share('appearance', $appearance->value);
        View::share('sidebarOpen', $request->cookie('sidebar_state') !== 'false');
        View::share('sidebarCss', $this->sidebarCss($request));

        // The document title and the favicon have to be in the first byte of
        // HTML for the same reason the palette does: swapping either one after
        // hydration is a visible flash in the browser tab.
        View::share('branding', app(Branding::class)->toArray());

        return $next($request);
    }

    /**
     * The palette for whichever panel this request belongs to.
     *
     * The console and the tenant app are themed independently, and the two are
     * told apart by the URL rather than the guard: the admin login screen has no
     * authenticated operator yet but is still unmistakably part of the console.
     */
    protected function sidebarCss(Request $request): string
    {
        $panel = $request->is('admin', 'admin/*') ? 'admin' : 'app';

        $key = app(SettingsRepository::class)->getFrom(
            SettingsRepository::SCOPE_SYSTEM,
            null,
            "panel_theme.{$panel}_sidebar",
            SidebarPalette::DEFAULT,
        );

        return SidebarPalette::css(is_string($key) ? $key : SidebarPalette::DEFAULT);
    }
}
