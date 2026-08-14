<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Platform\Http\Controllers\Auth\AdminTwoFactorChallengeController;
use App\Modules\Platform\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * The admin's own two-factor enrolment.
 *
 * Reuses Fortify's enable/confirm/disable primitives — they operate on any model
 * with the TwoFactorAuthenticatable trait — against the signed-in Admin. The
 * challenge at login lives in {@see AdminTwoFactorChallengeController}.
 */
class AdminTwoFactorController extends Controller
{
    public function __construct(protected SecurityLogger $security) {}

    public function store(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $admin = $this->admin($request);

        $enable($admin);

        $this->security->log(SecurityEvent::TwoFactorEnabled, null, __('Admin two-factor enrolment started'), [], $admin);

        return back()->with('success', __('Scan the QR code, then confirm with a code to finish.'));
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $admin = $this->admin($request);

        $validated = $request->validate(['code' => ['required', 'string']]);

        $confirm($admin, $validated['code']);

        $this->security->log(SecurityEvent::TwoFactorEnabled, null, __('Admin two-factor confirmed'), [], $admin);

        return back()->with('success', __('Two-factor authentication is now active.'));
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $admin = $this->admin($request);

        abort_unless($admin->hasEnabledTwoFactorAuthentication(), 404);

        return new JsonResponse(['codes' => $admin->recoveryCodes()]);
    }

    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $admin = $this->admin($request);

        abort_unless($admin->hasEnabledTwoFactorAuthentication(), 404);

        $generate($admin);

        $this->security->log(SecurityEvent::RecoveryCodesRegenerated, null, null, [], $admin);

        return back()->with('success', __('New recovery codes generated. Store them somewhere safe.'));
    }

    public function destroy(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $admin = $this->admin($request);

        $disable($admin);

        $this->security->log(SecurityEvent::TwoFactorDisabled, null, null, [], $admin);

        return back()->with('success', __('Two-factor authentication disabled.'));
    }

    protected function admin(Request $request): Admin
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
