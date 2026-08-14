<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Auth\Services\DeviceParser;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

/**
 * The user's own active sessions.
 *
 * Reads the `sessions` table directly, which means the feature is only
 * meaningful on the database session driver; on any other driver the screen
 * reports that rather than pretending the list is empty.
 */
class SessionController extends Controller
{
    public function __construct(
        protected DeviceParser $devices,
        protected SecurityLogger $security,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('settings/sessions', [
            'sessions' => $this->sessions($request),
            'supported' => $this->supported(),
        ]);
    }

    /**
     * Revoke a single session by its id.
     */
    public function destroy(Request $request, string $session): RedirectResponse
    {
        $user = $this->user($request);

        abort_unless($this->supported(), 404);

        if ($session === $request->session()->getId()) {
            throw ValidationException::withMessages([
                'session' => __('Use sign out to end the session you are currently using.'),
            ]);
        }

        $deleted = DB::table('sessions')
            ->where('id', $session)
            ->where('user_id', $user->id)
            ->delete();

        abort_if($deleted === 0, 404);

        $this->security->log(
            SecurityEvent::SessionRevoked,
            $user,
            __('Revoked another browser session'),
            ['session_id' => $session],
        );

        return back()->with('success', __('Session revoked.'));
    }

    /**
     * Revoke everything except the session making the request.
     *
     * @throws ValidationException
     */
    public function destroyOthers(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], (string) $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'password' => __('The provided password is incorrect.'),
            ]);
        }

        // Rotates the password hash held in the session, which invalidates every
        // other session and every remember-me cookie for this account.
        Auth::logoutOtherDevices($validated['password']);

        if ($this->supported()) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $user->forceFill(['remember_token' => null])->save();

        $this->security->log(
            SecurityEvent::OtherSessionsRevoked,
            $user,
            __('Revoked all other browser sessions'),
        );

        return back()->with('success', __('All other sessions have been signed out.'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function sessions(Request $request): array
    {
        if (! $this->supported()) {
            return [];
        }

        $currentId = $request->session()->getId();

        return DB::table('sessions')
            ->where('user_id', $this->user($request)->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (stdClass $session) use ($currentId): array {
                $agent = property_exists($session, 'user_agent') && is_string($session->user_agent)
                    ? $session->user_agent
                    : null;

                $parsed = $this->devices->parse($agent);
                $lastActivity = is_numeric($session->last_activity) ? (int) $session->last_activity : 0;

                return [
                    'id' => (string) $session->id,
                    'ip_address' => is_string($session->ip_address ?? null) ? $session->ip_address : null,
                    'device' => $parsed['device'],
                    'platform' => $parsed['platform'],
                    'browser' => $parsed['browser'],
                    'last_active_at' => Carbon::createFromTimestamp($lastActivity)->toIso8601String(),
                    'last_active_human' => Carbon::createFromTimestamp($lastActivity)->diffForHumans(),
                    'is_current' => (string) $session->id === $currentId,
                ];
            })
            ->values()
            ->all();
    }

    protected function supported(): bool
    {
        return config('session.driver') === 'database';
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
