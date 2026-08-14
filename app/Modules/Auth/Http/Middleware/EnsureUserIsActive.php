<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user who was suspended while signed in.
 *
 * Suspension has to take effect on the next request rather than at the next
 * login, otherwise revoking access to an active session would mean waiting for
 * it to expire on its own.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isSuspended()) {
            return $this->reject($request, $user);
        }

        return $next($request);
    }

    protected function reject(Request $request, User $user): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $user->suspended_reason === null
            ? __('Your account has been suspended. Please contact support.')
            : __('Your account has been suspended: :reason', ['reason' => $user->suspended_reason]);

        return redirect()->route('login')->with('error', $message);
    }
}
