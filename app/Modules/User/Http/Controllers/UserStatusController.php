<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Actions\DeleteUser;
use App\Modules\User\Actions\RestoreUser;
use App\Modules\User\Actions\SuspendUser;
use App\Modules\User\Http\Requests\SuspendUserRequest;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Lifecycle transitions that are not ordinary edits.
 *
 * Each one is written to the security log by its action, so the audit trail
 * cannot be bypassed by reaching for the model directly.
 */
class UserStatusController extends Controller
{
    public function suspend(SuspendUserRequest $request, User $user, SuspendUser $suspendUser): RedirectResponse
    {
        $suspendUser->handle($user, (string) $request->string('reason'));

        return back()->with('success', __('User suspended.'));
    }

    public function restore(User $user, RestoreUser $restoreUser): RedirectResponse
    {
        $this->authorize('restore', $user);

        $restoreUser->handle($user);

        return back()->with('success', __('User restored.'));
    }

    public function forceDelete(User $user, DeleteUser $deleteUser): RedirectResponse
    {
        $this->authorize('forceDelete', $user);

        $deleteUser->handle($user, permanent: true);

        return redirect()
            ->route('users.index')
            ->with('success', __('User permanently deleted.'));
    }
}
