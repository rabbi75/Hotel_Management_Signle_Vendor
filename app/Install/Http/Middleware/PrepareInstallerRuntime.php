<?php

declare(strict_types=1);

namespace App\Install\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the wizard usable before Redis/DB session drivers are ready.
 */
class PrepareInstallerRuntime
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.installed')) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);
        }

        return $next($request);
    }
}
