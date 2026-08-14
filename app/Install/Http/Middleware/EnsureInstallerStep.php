<?php

declare(strict_types=1);

namespace App\Install\Http\Middleware;

use App\Install\Support\InstallerState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallerStep
{
    public function __construct(protected InstallerState $state) {}

    public function handle(Request $request, Closure $next, string $step): Response
    {
        if (! $this->state->canAccess($step)) {
            return redirect()->route('install.requirements')
                ->with('error', __('Complete the previous installer steps first.'));
        }

        return $next($request);
    }
}
