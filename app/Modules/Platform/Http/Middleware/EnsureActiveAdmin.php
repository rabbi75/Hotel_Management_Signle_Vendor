<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a suspended admin out of the console immediately.
 *
 * `auth:admin` guarantees an authenticated admin; this guarantees an *active*
 * one, so deactivating an admin takes effect on their next request rather than
 * their next login.
 */
class EnsureActiveAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin instanceof Admin && $admin->isSuspended()) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => __('This account has been deactivated.')]);
        }

        return $next($request);
    }
}
