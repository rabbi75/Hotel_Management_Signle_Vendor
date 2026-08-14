<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Platform\Actions\ImpersonateTenant;
use App\Modules\Platform\Models\Admin;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * End an impersonation, whichever kind it is.
 *
 * Two flows land here through the same banner button:
 *  - tenant-internal (web → web): a User impersonating a workspace member, started
 *    by {@see StartImpersonation}. The original User is restored.
 *  - console (admin → web): an Admin standing in for a tenant, started by
 *    {@see ImpersonateTenant}. The web guard is simply logged out; the admin
 *    guard was never disturbed, so the operator is back in the console.
 */
class StopImpersonation
{
    public function __construct(protected SecurityLogger $security) {}

    /**
     * @return string|null The URL the impersonation was started from, if any.
     */
    public function handle(Request $request): ?string
    {
        if ($request->session()->has(ImpersonateTenant::ADMIN_SESSION_KEY)) {
            return $this->stopAdminImpersonation($request);
        }

        return $this->stopUserImpersonation($request);
    }

    protected function stopAdminImpersonation(Request $request): ?string
    {
        $adminId = $request->session()->pull(ImpersonateTenant::ADMIN_SESSION_KEY);
        $returnUrl = $request->session()->pull(ImpersonateTenant::RETURN_KEY);
        // The impersonated tenant is on the web guard; the default guard here may
        // be the console's, so resolve it explicitly for an accurate audit line.
        $impersonated = $request->user('web');
        $admin = is_int($adminId) ? Admin::query()->find($adminId) : null;

        // Drop only the web session; the admin guard is untouched.
        Auth::guard('web')->logout();
        $request->session()->regenerate();
        $request->session()->forget((string) config('saas.workspace.session_key'));

        $this->security->log(
            SecurityEvent::ImpersonationStopped,
            null,
            __('Admin stopped impersonating :email.', ['email' => $impersonated?->email]),
            ['target_user_id' => $impersonated?->getAuthIdentifier()],
            $admin instanceof Admin ? $admin : null,
        );

        return is_string($returnUrl) ? $returnUrl : null;
    }

    protected function stopUserImpersonation(Request $request): ?string
    {
        $impersonatorId = $request->session()->pull(StartImpersonation::SESSION_KEY);

        abort_if(! is_int($impersonatorId), 403, __('You are not impersonating anyone.'));

        $impersonator = User::query()->findOrFail($impersonatorId);
        $impersonated = $request->user();
        $returnUrl = $request->session()->pull(StartImpersonation::RETURN_KEY);

        Auth::login($impersonator);
        $request->session()->regenerate();
        $request->session()->forget((string) config('saas.workspace.session_key'));

        $this->security->log(
            SecurityEvent::ImpersonationStopped,
            $impersonator,
            __('Stopped impersonating :email.', ['email' => $impersonated?->email]),
            ['target_user_id' => $impersonated?->getAuthIdentifier()],
        );

        return is_string($returnUrl) ? $returnUrl : null;
    }
}
