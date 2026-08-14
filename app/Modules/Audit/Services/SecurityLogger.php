<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\Platform\Models\Admin;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Single entry point for writing the security log.
 *
 * Request metadata (IP, user agent, workspace) is captured here so no caller
 * has to remember to attach it, and the event vocabulary stays confined to
 * {@see SecurityEvent}.
 */
class SecurityLogger
{
    public function __construct(protected Request $request) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  Admin|null  $admin  The console operator behind the action, when it
     *                             is taken from the platform panel rather than by a
     *                             tenant. Recorded alongside (or instead of) the
     *                             tenant user so the entry is always attributable.
     */
    public function log(
        SecurityEvent $event,
        Authenticatable|User|null $user = null,
        ?string $description = null,
        array $context = [],
        ?Admin $admin = null,
    ): SecurityLog {
        // An actor is routed by what it *is*, not by which parameter it arrived
        // in. Callers commonly pass `$request->user()` or `Auth::user()`, and in
        // the console the request's default guard is `admin` — so the "tenant
        // user" argument is an Admin there. Writing that id into `user_id` would
        // point the row at whichever unrelated user shares the number, and where
        // the ids do not line up it is a foreign-key violation instead.
        $admin ??= $user instanceof Admin ? $user : null;
        $user = $user instanceof Admin ? null : $user;

        return SecurityLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'admin_id' => $admin?->getKey(),
            'company_id' => $context['company_id'] ?? current_company_id(),
            'event' => $event,
            'severity' => $event->severity(),
            'description' => $description ?? $event->label(),
            'context' => $context === [] ? null : $context,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 1000),
        ]);
    }
}
