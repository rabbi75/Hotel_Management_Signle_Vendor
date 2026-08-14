<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Platform\Models\Admin;
use App\Modules\Platform\Models\AdminLoginHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Shared tail of every admin sign-in path (password, two-factor).
 *
 * Logs the guard in, regenerates the session, records a login-history row and a
 * security-log entry, and stamps last-seen. One place so the password flow and
 * the two-factor flow can never drift.
 */
trait CompletesAdminLogin
{
    protected function completeLogin(Request $request, Admin $admin, bool $remember = false): void
    {
        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        $this->recordLogin($admin, successful: true);

        app(SecurityLogger::class)->log(
            SecurityEvent::AdminLoggedIn,
            null,
            __('Admin :email signed in.', ['email' => $admin->email]),
            [],
            $admin,
        );
    }

    protected function recordLogin(?Admin $admin, bool $successful): void
    {
        if (! $admin instanceof Admin) {
            return;
        }

        $request = request();
        $agent = (string) $request->userAgent();

        AdminLoginHistory::create([
            'admin_id' => $admin->id,
            'ip_address' => $request->ip(),
            'platform' => $this->uaPlatform($agent),
            'browser' => $this->uaBrowser($agent),
            'successful' => $successful,
            'logged_in_at' => now(),
        ]);
    }

    protected function uaPlatform(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Windows NT') => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod/i', $agent) => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') => 'macOS',
            (bool) preg_match('/Linux/i', $agent) => 'Linux',
            default => 'Unknown',
        };
    }

    protected function uaBrowser(string $agent): string
    {
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
