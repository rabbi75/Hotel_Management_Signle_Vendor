<?php

declare(strict_types=1);

namespace App\Install\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks /install/* once the product is marked installed.
 */
class RedirectIfInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.installed')) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
