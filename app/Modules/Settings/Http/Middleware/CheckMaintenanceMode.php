<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Middleware;

use App\Modules\Platform\Models\Admin;
use App\Support\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Enforces the settings-driven maintenance window.
 *
 * Three ways past it, in order of cost: an allow-listed IP, the bypass secret
 * (presented once as `?maintenance_secret=`, then remembered in a cookie), or
 * an authenticated operator who can toggle maintenance in the first place —
 * otherwise nobody could turn it back off.
 *
 * The secret arrives as a query parameter rather than as a path, the way
 * `artisan down --secret` does it: this middleware runs in the `web` group, and
 * a bare token path matches no route, so the router would 404 before it ever
 * got a say.
 */
class CheckMaintenanceMode
{
    public const BYPASS_COOKIE = 'maintenance_bypass';

    public const BYPASS_PARAMETER = 'maintenance_secret';

    public function __construct(protected SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Reading settings requires the database; during install or a migration
        // the app must still boot, so a failure here means "not in maintenance".
        $enabled = rescue(fn (): bool => (bool) $this->settings->get('maintenance.enabled', false), false, false);

        if (! $enabled) {
            return $next($request);
        }

        $secret = $this->secret();

        if ($secret !== null && $request->query(self::BYPASS_PARAMETER) === $secret) {
            // Redirect rather than serve: it drops the secret out of the URL
            // before it can reach a referrer header or the browser history.
            return redirect($request->url())->withCookie(
                new Cookie(self::BYPASS_COOKIE, $secret, time() + 43200, '/', null, $request->isSecure(), true)
            );
        }

        if ($this->isExempt($request, $secret)) {
            return $next($request);
        }

        throw new ServiceUnavailableHttpException(
            $this->retryAfter(),
            (string) ($this->settings->get('maintenance.message') ?: __('The application is undergoing scheduled maintenance.')),
        );
    }

    protected function isExempt(Request $request, ?string $secret): bool
    {
        if ($secret !== null && $request->cookie(self::BYPASS_COOKIE) === $secret) {
            return true;
        }

        if (in_array((string) $request->ip(), $this->allowedIps(), true)) {
            return true;
        }

        // Only an operator works through a maintenance window. A tenant user —
        // even a workspace owner — is exactly who the screen is up for.
        $admin = $request->user('admin');

        return $admin instanceof Admin && $admin->can('platform.settings.maintenance');
    }

    protected function secret(): ?string
    {
        $secret = $this->settings->get('maintenance.secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /**
     * @return list<string>
     */
    protected function allowedIps(): array
    {
        $ips = $this->settings->get('maintenance.allowed_ips', []);

        return is_array($ips) ? array_values(array_map(strval(...), $ips)) : [];
    }

    protected function retryAfter(): int
    {
        $retry = $this->settings->get('maintenance.retry_after', 3600);

        return is_numeric($retry) ? (int) $retry : 3600;
    }
}
