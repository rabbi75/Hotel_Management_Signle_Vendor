<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Admin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Forgot / reset password for the console, on the `admins` broker.
 *
 * A separate broker (and token table) from the tenant flow, so a reset link for
 * one world can never be redeemed in the other.
 */
class AdminPasswordResetController extends Controller
{
    public function requestView(Request $request): Response
    {
        return Inertia::render('admin/auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = $this->broker()->sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return back()->with('status', __($status));
    }

    public function resetView(Request $request): Response
    {
        return Inertia::render('admin/auth/reset-password', [
            'email' => $request->string('email')->toString(),
            'token' => (string) $request->route('token'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::default()],
        ]);

        $status = $this->broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Admin $admin, string $password): void {
                $admin->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($admin));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('admin.login')->with('status', __($status));
    }

    protected function broker()
    {
        return Password::broker('admins');
    }
}
