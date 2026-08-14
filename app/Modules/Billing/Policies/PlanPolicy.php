<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Modules\Billing\Models\Plan;
use App\Modules\User\Models\User;

/**
 * Plans are catalogue data, not workspace data, so there is no tenant check
 * here — only the permission that separates browsing from editing.
 */
class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['billing.view', 'billing.plans.manage']);
    }

    public function view(User $user, Plan $plan): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $user->can('billing.plans.manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Plan $plan): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $this->manage($user);
    }
}
