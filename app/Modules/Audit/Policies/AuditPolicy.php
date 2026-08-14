<?php

declare(strict_types=1);

namespace App\Modules\Audit\Policies;

use App\Modules\User\Models\User;

/**
 * Authorisation for the three audit screens.
 *
 * Registered against all three subject models with deliberately distinct
 * ability names, because a shared `viewAny` cannot tell which log is being
 * asked for once the class name has been resolved to a policy.
 */
class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['audit.activity.view', 'audit.login.view', 'audit.security.view']);
    }

    public function viewActivity(User $user): bool
    {
        return $user->can('audit.activity.view');
    }

    public function viewLogins(User $user): bool
    {
        return $user->can('audit.login.view');
    }

    public function viewSecurity(User $user): bool
    {
        return $user->can('audit.security.view');
    }

    public function export(User $user): bool
    {
        return $user->can('audit.export');
    }
}
