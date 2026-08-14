<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\Models\Company;
use App\Modules\Platform\Models\Admin;
use App\Modules\User\Actions\StartImpersonation;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Log in as tenant", across guards.
 *
 * The operator stays authenticated on the `admin` guard; a *second* login is
 * opened on the `web` guard as the workspace owner. Both coexist in one session,
 * which is exactly what lets the banner drop the tenant session on "Return to
 * admin" without disturbing the console login. Distinct from the tenant-internal
 * {@see StartImpersonation} (web → web).
 */
class ImpersonateTenant
{
    /** Marks a web session as an admin-driven impersonation. Holds the admin id. */
    public const ADMIN_SESSION_KEY = 'impersonating_admin_id';

    /** Where "Return to admin" sends the operator back to. */
    public const RETURN_KEY = 'impersonation_return_url';

    public function __construct(protected SecurityLogger $security) {}

    public function handle(Request $request, Admin $admin, Company $company, User $owner, string $returnUrl): void
    {
        // Refuse to nest: a web session already standing in for someone must not
        // be reused for a second impersonation.
        abort_if(
            $request->session()->has(self::ADMIN_SESSION_KEY)
                || $request->session()->has(StartImpersonation::SESSION_KEY),
            403,
            __('You are already impersonating a user.'),
        );

        $this->security->log(
            SecurityEvent::ImpersonationStarted,
            $owner,
            __('Admin :admin signed in as :company.', ['admin' => $admin->email, 'company' => $company->name]),
            ['company_id' => $company->id, 'target_user_id' => $owner->id],
            $admin,
        );

        Auth::guard('web')->login($owner);
        $request->session()->regenerate();

        $request->session()->put((string) config('saas.workspace.session_key'), $company->id);
        $request->session()->put(self::ADMIN_SESSION_KEY, $admin->id);
        $request->session()->put(self::RETURN_KEY, $returnUrl);
    }
}
