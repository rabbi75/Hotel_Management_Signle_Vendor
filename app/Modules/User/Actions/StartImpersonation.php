<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sign an administrator in as another user.
 *
 * The original identity is parked in the session rather than in a cookie or a
 * signed URL, so ending the session — by logout or expiry — also ends the
 * impersonation. Shared by the workspace user screens and the platform panel so
 * there is exactly one way in, and one audit trail.
 */
class StartImpersonation
{
    public const SESSION_KEY = 'impersonator_id';

    /** Where "Return to admin" sends the impersonator back to. */
    public const RETURN_KEY = 'impersonation_return_url';

    public function __construct(protected SecurityLogger $security) {}

    /**
     * @param  Company|null  $workspace  The workspace to land in. Null leaves the
     *                                   target's own last-used workspace to be
     *                                   resolved by SetCurrentCompany.
     */
    public function handle(
        Request $request,
        User $impersonator,
        User $target,
        ?Company $workspace = null,
        ?string $returnUrl = null,
    ): void {
        // Nesting would lose the real identity at the bottom of the stack, so a
        // second hop is refused outright.
        abort_if($request->session()->has(self::SESSION_KEY), 403, __('You are already impersonating a user.'));

        $this->security->log(
            SecurityEvent::ImpersonationStarted,
            $impersonator,
            __('Started impersonating :email.', ['email' => $target->email]),
            [
                'target_user_id' => $target->id,
                'company_id' => $workspace?->id,
            ],
        );

        Auth::login($target);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $impersonator->id);

        $workspaceKey = (string) config('saas.workspace.session_key');

        // A workspace-targeted impersonation must land in that workspace; a
        // user-targeted one must not inherit the administrator's.
        $workspace instanceof Company
            ? $request->session()->put($workspaceKey, $workspace->id)
            : $request->session()->forget($workspaceKey);

        if ($returnUrl !== null) {
            $request->session()->put(self::RETURN_KEY, $returnUrl);
        }
    }
}
