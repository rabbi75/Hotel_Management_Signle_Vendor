<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\User\Models\LoginHistory;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;

/**
 * Writes the authentication trail.
 *
 * The user agent is parsed here — rather than at read time — so the login
 * history stays queryable ("show me every login from an iPad") without
 * re-parsing a text column on every request.
 */
class LoginHistoryRecorder
{
    public function __construct(protected Request $request) {}

    public function recordSuccess(User $user, bool $twoFactorUsed = false): LoginHistory
    {
        $history = LoginHistory::create(array_merge($this->context(), [
            'user_id' => $user->id,
            'email' => $user->email,
            'successful' => true,
            'two_factor_used' => $twoFactorUsed,
            'logged_in_at' => now(),
        ]));

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
        ])->saveQuietly();

        return $history;
    }

    /**
     * A failed attempt is recorded even when no account matched, so credential
     * stuffing against non-existent addresses is still visible.
     */
    public function recordFailure(?string $email, ?User $user = null, string $reason = 'invalid_credentials'): LoginHistory
    {
        return LoginHistory::create(array_merge($this->context(), [
            'user_id' => $user?->id,
            'email' => $email,
            'successful' => false,
            'failure_reason' => substr($reason, 0, 64),
            'logged_in_at' => now(),
        ]));
    }

    /**
     * Close the row this session opened.
     *
     * Matching on session id rather than "the user's most recent login" keeps
     * concurrent sessions from closing each other. Exactly one row is stamped —
     * the newest still-open one — because a session id is only unique while it
     * is alive: PHP reissues ids, and a login/logout/login cycle in the same
     * browser can legitimately leave two rows carrying the same one. Stamping
     * both would backdate a session that never ended when it says it did.
     */
    public function recordLogout(?User $user, ?string $sessionId): void
    {
        if ($sessionId === null) {
            return;
        }

        $query = LoginHistory::query()
            ->where('session_id', $sessionId)
            ->whereNull('logged_out_at');

        if ($user instanceof User) {
            $query->where('user_id', $user->id);
        }

        $history = $query->latest('logged_in_at')->first();

        $history?->forceFill(['logged_out_at' => now()])->save();
    }

    /**
     * @return array{ip_address: string|null, user_agent: string|null, device_type: string, platform: string, browser: string, session_id: string|null}
     */
    protected function context(): array
    {
        $agent = (string) $this->request->userAgent();

        return [
            'ip_address' => $this->request->ip(),
            'user_agent' => $agent === '' ? null : substr($agent, 0, 1000),
            'device_type' => $this->deviceType($agent),
            'platform' => $this->platform($agent),
            'browser' => $this->browser($agent),
            'session_id' => $this->request->hasSession() ? $this->request->session()->getId() : null,
        ];
    }

    protected function deviceType(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/(bot|crawler|spider|curl|wget|postman|insomnia)/i', $agent) => 'bot',
            (bool) preg_match('/(ipad|tablet|playbook|silk)|(android(?!.*mobile))/i', $agent) => 'tablet',
            (bool) preg_match('/(mobile|iphone|ipod|android|blackberry|windows phone)/i', $agent) => 'mobile',
            $agent === '' => 'unknown',
            default => 'desktop',
        };
    }

    protected function platform(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Windows NT') => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod/i', $agent) => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') => 'macOS',
            (bool) preg_match('/CrOS/i', $agent) => 'ChromeOS',
            (bool) preg_match('/Ubuntu|Debian|Fedora|Linux/i', $agent) => 'Linux',
            default => 'Unknown',
        };
    }

    protected function browser(string $agent): string
    {
        // Order matters: every Chromium browser also claims to be Chrome, and
        // Chrome also claims to be Safari.
        return match (true) {
            (bool) preg_match('/Edg[e\/]/i', $agent) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $agent) => 'Opera',
            (bool) preg_match('/Firefox\//i', $agent) => 'Firefox',
            (bool) preg_match('/Chrome\//i', $agent) => 'Chrome',
            (bool) preg_match('/Safari\//i', $agent) => 'Safari',
            default => 'Unknown',
        };
    }
}
