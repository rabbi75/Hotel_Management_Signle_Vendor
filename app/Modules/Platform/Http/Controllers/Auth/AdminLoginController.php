<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Admin;
use App\Modules\Platform\Support\CompletesAdminLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The console's own sign-in, on the `admin` guard.
 *
 * Not Fortify — Fortify serves the single `web` guard. This is the compact
 * equivalent for admins: credential check, the two-factor hand-off, and the
 * login-history row, mirroring the tenant flow's behaviour without sharing its
 * guard or its user table.
 */
class AdminLoginController extends Controller
{
    use CompletesAdminLogin;

    public function create(Request $request): Response
    {
        return Inertia::render('admin/auth/login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::query()->where('email', $credentials['email'])->first();

        if (! $admin instanceof Admin || ! Hash::check($credentials['password'], $admin->password)) {
            $this->recordLogin($admin, successful: false);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if ($admin->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => __('This account has been deactivated.'),
            ]);
        }

        // Two-factor: park the pending identity and hand off to the challenge
        // rather than authenticating now. The session key is namespaced so it
        // never collides with the tenant guard's `login.id`.
        if ($admin->hasTwoFactorEnabled()) {
            $request->session()->put('admin_login.id', $admin->id);
            $request->session()->put('admin_login.remember', $request->boolean('remember'));

            return redirect()->route('admin.two-factor.challenge');
        }

        $this->completeLogin($request, $admin, $request->boolean('remember'));

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
