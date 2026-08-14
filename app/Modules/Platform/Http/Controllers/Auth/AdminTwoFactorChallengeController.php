<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Admin;
use App\Modules\Platform\Support\CompletesAdminLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * The second factor for an admin sign-in.
 *
 * Reached only after {@see AdminLoginController} has verified the password and
 * parked the pending admin id in `admin_login.id`. Accepts either a TOTP code or
 * one of the admin's recovery codes, then completes the login. Mirrors the
 * tenant challenge but against the `admin` guard.
 */
class AdminTwoFactorChallengeController extends Controller
{
    use CompletesAdminLogin;

    public function create(Request $request): Response
    {
        abort_unless($request->session()->has('admin_login.id'), 403);

        return Inertia::render('admin/auth/two-factor-challenge');
    }

    public function store(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $adminId = $request->session()->get('admin_login.id');

        abort_unless(is_int($adminId), 403);

        $admin = Admin::query()->findOrFail($adminId);

        $validated = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $verified = $this->verifyCode($provider, $admin, $validated['code'] ?? null)
            || $this->verifyRecoveryCode($admin, $validated['recovery_code'] ?? null);

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => __('The provided two-factor code was invalid.'),
            ]);
        }

        $remember = (bool) $request->session()->pull('admin_login.remember', false);
        $request->session()->forget('admin_login.id');

        $this->completeLogin($request, $admin, $remember);

        return redirect()->intended(route('admin.dashboard'));
    }

    protected function verifyCode(TwoFactorAuthenticationProvider $provider, Admin $admin, ?string $code): bool
    {
        if ($code === null || $code === '' || $admin->two_factor_secret === null) {
            return false;
        }

        return $provider->verify(
            Fortify::currentEncrypter()->decrypt($admin->two_factor_secret),
            $code,
        );
    }

    protected function verifyRecoveryCode(Admin $admin, ?string $recoveryCode): bool
    {
        if ($recoveryCode === null || $recoveryCode === '' || $admin->two_factor_recovery_codes === null) {
            return false;
        }

        /** @var Collection<int, string> $codes */
        $codes = collect(json_decode(
            Fortify::currentEncrypter()->decrypt($admin->two_factor_recovery_codes),
            true,
        ));

        if (! $codes->contains($recoveryCode)) {
            return false;
        }

        // A recovery code is single-use — burn it.
        $admin->forceFill([
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(
                $codes->reject(fn (string $code): bool => $code === $recoveryCode)->values()->toJson(),
            ),
        ])->saveQuietly();

        return true;
    }
}
