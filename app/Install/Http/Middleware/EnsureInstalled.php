<?php

declare(strict_types=1);

namespace App\Install\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces the rest of the application behind the installer until APP_INSTALLED=true.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('installer.enabled', true)) {
            return $next($request);
        }

        if (config('app.installed')) {
            return $next($request);
        }

        if ($request->is('install', 'install/*', 'up')) {
            return $next($request);
        }

        return redirect()->route('install.requirements');
    }
}
