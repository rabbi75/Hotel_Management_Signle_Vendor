<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-challenges an operator for their password before the most damaging panels.
 *
 * Fortify ships this for the `web` guard only, and its screen authenticates a
 * tenant `User` — pointing the console at it would send an operator somewhere
 * they cannot satisfy. This is the same idea on the `admin` guard: a stolen
 * session should not be enough to read the Stripe secret or rewrite the
 * password policy.
 */
class ConfirmAdminPassword
{
    public const SESSION_KEY = 'admin_password_confirmed_at';

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->recentlyConfirmed($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Password confirmation required.')], 423);
        }

        return redirect()
            ->route($this->confirmRoute())
            ->with('intended_url', $request->fullUrl());
    }

    protected function recentlyConfirmed(Request $request): bool
    {
        foreach ([self::SESSION_KEY, 'auth.password_confirmed_at'] as $key) {
            $confirmedAt = $request->session()->get($key);

            if (! is_int($confirmedAt) && ! is_numeric($confirmedAt)) {
                continue;
            }

            $timeout = (int) config('auth.password_timeout', 10800);

            if ((time() - (int) $confirmedAt) < $timeout) {
                return true;
            }
        }

        return false;
    }

    protected function confirmRoute(): string
    {
        return Route::has('admin.password.confirm') ? 'admin.password.confirm' : 'password.confirm';
    }
}
