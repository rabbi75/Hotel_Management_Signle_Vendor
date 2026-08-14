<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Actions\StartImpersonation;
use App\Modules\User\Actions\StopImpersonation;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Signs an administrator in as another user and back out again.
 *
 * The mechanics live in {@see StartImpersonation} / {@see StopImpersonation} so
 * the platform panel's "log in as tenant" is the same code path — and therefore
 * the same audit trail — as impersonating a member from the user screens.
 */
class UserImpersonationController extends Controller
{
    /** @deprecated Use {@see StartImpersonation::SESSION_KEY}. Kept for callers that reference it. */
    public const SESSION_KEY = StartImpersonation::SESSION_KEY;

    public function store(Request $request, User $user, StartImpersonation $start): RedirectResponse
    {
        $this->authorize('impersonate', $user);

        $impersonator = $request->user();

        abort_if($impersonator === null, 403);

        // No workspace and no return URL: this is user-to-user impersonation, so
        // ending it lands back on the user index, as it always has.
        $start->handle($request, $impersonator, $user);

        return redirect()->route('profile.show')
            ->with('warning', __('You are now impersonating :name.', ['name' => $user->name]));
    }

    public function destroy(Request $request, StopImpersonation $stop): RedirectResponse
    {
        $returnUrl = $stop->handle($request);

        return redirect()->to($returnUrl ?? route('users.index'))
            ->with('success', __('Impersonation ended.'));
    }
}
