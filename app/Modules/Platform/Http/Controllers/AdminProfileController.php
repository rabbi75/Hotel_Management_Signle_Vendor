<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Platform\Http\Resources\AdminResource;
use App\Modules\Platform\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

/**
 * The signed-in admin's own account: profile, password, and two-factor enrolment.
 */
class AdminProfileController extends Controller
{
    public function __construct(protected SecurityLogger $security) {}

    public function edit(Request $request): Response
    {
        $admin = $this->admin($request);

        return Inertia::render('admin/account', [
            'admin' => (new AdminResource($admin))->resolve($request),
            'two_factor' => [
                'enabled' => $admin->hasEnabledTwoFactorAuthentication(),
                'confirmed' => $admin->two_factor_confirmed_at !== null,
                'pending' => $admin->two_factor_secret !== null && $admin->two_factor_confirmed_at === null,
                'qrCodeSvg' => $this->enrolling($admin) ? $admin->twoFactorQrCodeSvg() : null,
                'secret' => $this->enrolling($admin)
                    ? Fortify::currentEncrypter()->decrypt($admin->two_factor_secret)
                    : null,
            ],
            'login_history' => $admin->loginHistories()->limit(10)->get()->map(static fn ($entry): array => [
                'id' => $entry->id,
                'ip_address' => $entry->ip_address,
                'platform' => $entry->platform,
                'browser' => $entry->browser,
                'successful' => $entry->successful,
                'logged_in_at' => $entry->logged_in_at->toIso8601String(),
            ])->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $this->admin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('admins', 'email')->ignore($admin->id)],
        ]);

        $admin->update($validated);

        return back()->with('success', __('Profile updated.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $admin = $this->admin($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::default()],
        ]);

        if (! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The provided password does not match your current password.'),
            ]);
        }

        $admin->forceFill(['password' => Hash::make($validated['password'])])->save();

        $this->security->log(SecurityEvent::PasswordChanged, null, __('Admin password changed.'), [], $admin);

        return back()->with('success', __('Password updated.'));
    }

    protected function enrolling(Admin $admin): bool
    {
        return $admin->two_factor_secret !== null && $admin->two_factor_confirmed_at === null;
    }

    protected function admin(Request $request): Admin
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
