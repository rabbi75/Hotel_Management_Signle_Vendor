<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\LoginHistory;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Replaces Fortify's logout so the login-history row for this session is closed.
 *
 * Fortify's own handler ends the session and nothing else, which would leave
 * every history row with an open-ended `logged_out_at` and make session
 * duration unknowable.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $sessionId = $request->session()->getId();

        if ($user instanceof User) {
            LoginHistory::query()
                ->where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->whereNull('logged_out_at')
                ->update(['logged_out_at' => now()]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
