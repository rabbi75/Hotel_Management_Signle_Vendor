<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\Actions\UpdateAvatar;
use App\Modules\User\Actions\UpdatePreferences;
use App\Modules\User\Actions\UpdateUserProfileInformation;
use App\Modules\User\DTOs\PreferencesData;
use App\Modules\User\DTOs\ProfileData;
use App\Modules\User\Http\Requests\UpdatePreferencesRequest;
use App\Modules\User\Http\Requests\UpdateProfileRequest;
use App\Modules\User\Http\Requests\UploadAvatarRequest;
use App\Modules\User\Http\Resources\UserResource;
use App\Support\Enums\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's own account.
 *
 * Nothing here is permission gated — every user owns their profile — but each
 * endpoint still resolves the subject from the session rather than from the
 * request, so a crafted payload cannot redirect an edit onto someone else.
 */
class ProfileController extends Controller
{
    public function __construct(protected SecurityLogger $security) {}

    public function show(Request $request): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $user->load('roles');

        return Inertia::render('profile/show', [
            'user' => (new UserResource($user))->resolve(),
            'preferences' => [
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'theme' => $user->theme->value,
                'notifications' => (array) ($user->preferences['notifications'] ?? []),
            ],
            'locales' => config('saas.locales'),
            'themes' => Theme::options(),
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateUserProfileInformation $updater): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $updater->update($user, ProfileData::fromRequest($request)->toAttributes());

        return back()->with('success', __('Profile updated.'));
    }

    public function updatePreferences(UpdatePreferencesRequest $request, UpdatePreferences $updatePreferences): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $updatePreferences->handle($user, PreferencesData::fromRequest($request));

        return back()->with('success', __('Preferences saved.'));
    }

    public function storeAvatar(UploadAvatarRequest $request, UpdateAvatar $updateAvatar): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $file = $request->file('avatar');

        $updateAvatar->handle($user, $file instanceof UploadedFile ? $file : null);

        return back()->with('success', __('Avatar updated.'));
    }

    public function destroyAvatar(Request $request, UpdateAvatar $updateAvatar): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $updateAvatar->handle($user, null);

        return back()->with('success', __('Avatar removed.'));
    }

    /**
     * @throws ValidationException
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $request->validate(
            ['password' => ['required', 'current_password']],
            ['password.current_password' => __('The password you entered is incorrect.')],
        );

        // Owning a workspace makes the account load-bearing for other people;
        // ownership has to be transferred before it can be removed.
        if ($user->ownedCompanies()->exists()) {
            throw ValidationException::withMessages([
                'password' => __('Transfer or delete the workspaces you own before deleting your account.'),
            ]);
        }

        $this->security->log(
            SecurityEvent::AccountDeleted,
            $user,
            __('Account deleted by its owner.'),
            ['user_id' => $user->id, 'email' => $user->email],
        );

        Auth::logout();

        $user->tokens()->delete();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', __('Your account has been deleted.'));
    }
}
