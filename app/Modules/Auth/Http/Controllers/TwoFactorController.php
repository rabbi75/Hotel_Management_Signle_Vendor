<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

/**
 * The two-factor settings screen.
 *
 * Fortify already owns the enable/confirm/disable primitives; this controller
 * exists to give them an Inertia surface, a single place to write the security
 * log, and a status payload the UI can render without guessing at the model's
 * internal columns.
 */
class TwoFactorController extends Controller
{
    public function __construct(protected SecurityLogger $security) {}

    public function show(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('settings/two-factor', [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'confirmed' => $user->two_factor_confirmed_at !== null,
            'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            // Only exposed while enrolment is in progress; once confirmed the
            // secret must never travel to the client again.
            'qrCodeSvg' => $this->enrolling($user) ? $user->twoFactorQrCodeSvg() : null,
            'secret' => $this->enrolling($user)
                ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret)
                : null,
        ]);
    }

    public function store(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $user = $this->user($request);

        $enable($user);

        $this->security->log(SecurityEvent::TwoFactorEnabled, $user, __('Two-factor enrolment started'));

        return back()->with('success', __('Scan the QR code, then confirm with a code to finish.'));
    }

    /**
     * @throws ValidationException
     */
    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $confirm($user, $validated['code']);

        $this->security->log(SecurityEvent::TwoFactorEnabled, $user, __('Two-factor authentication confirmed'));

        return back()->with('success', __('Two-factor authentication is now active.'));
    }

    /**
     * Recovery codes are only ever returned in response to an explicit request,
     * never as part of the page payload.
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $this->user($request);

        abort_unless($user->hasEnabledTwoFactorAuthentication(), 404);

        return new JsonResponse(['codes' => $user->recoveryCodes()]);
    }

    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $user = $this->user($request);

        abort_unless($user->hasEnabledTwoFactorAuthentication(), 404);

        $generate($user);

        $this->security->log(SecurityEvent::RecoveryCodesRegenerated, $user);

        return back()->with('success', __('New recovery codes generated. Store them somewhere safe.'));
    }

    public function destroy(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $user = $this->user($request);

        $disable($user);

        $this->security->log(SecurityEvent::TwoFactorDisabled, $user);

        return back()->with('success', __('Two-factor authentication disabled.'));
    }

    protected function enrolling(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
