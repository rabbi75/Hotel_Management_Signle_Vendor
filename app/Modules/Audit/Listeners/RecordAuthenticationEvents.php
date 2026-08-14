<?php

declare(strict_types=1);

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Services\LoginHistoryRecorder;
use App\Modules\User\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Turns Laravel's auth events into login-history rows.
 *
 * Listening to the framework's events rather than instrumenting the login
 * controller means alternative entry points — social login, passkeys, remember
 * tokens, impersonation — are recorded for free.
 */
class RecordAuthenticationEvents
{
    public function __construct(protected LoginHistoryRecorder $recorder) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->recorder->recordSuccess(
            $event->user,
            $event->user->hasEnabledTwoFactorAuthentication(),
        );
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $this->recorder->recordFailure(
            is_string($email) ? $email : null,
            $event->user instanceof User ? $event->user : null,
            // Distinguishing the two makes credential stuffing against
            // non-existent accounts visible as its own pattern.
            $event->user === null ? 'unknown_account' : 'invalid_credentials',
        );
    }

    public function handleLogout(Logout $event): void
    {
        $this->recorder->recordLogout(
            $event->user instanceof User ? $event->user : null,
            request()->hasSession() ? request()->session()->getId() : null,
        );
    }

    /**
     * Recorded as a distinct failure reason rather than a security-log entry:
     * SecurityEvent has no lockout case, and widening that closed vocabulary
     * is not this listener's call to make.
     */
    public function handleLockout(Lockout $event): void
    {
        $email = $event->request->input('email');

        $this->recorder->recordFailure(
            is_string($email) ? $email : null,
            null,
            'throttled',
        );
    }
}
