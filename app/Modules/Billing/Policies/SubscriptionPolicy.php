<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Modules\Billing\Models\Subscription;
use App\Modules\User\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.view');
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $this->inCurrentWorkspace($subscription) && $user->can('billing.view');
    }

    public function create(User $user): bool
    {
        return $user->can('billing.subscribe');
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $this->inCurrentWorkspace($subscription) && $user->can('billing.subscribe');
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $this->inCurrentWorkspace($subscription) && $user->can('billing.cancel');
    }

    public function resume(User $user, Subscription $subscription): bool
    {
        return $this->inCurrentWorkspace($subscription) && $user->can('billing.subscribe');
    }

    /**
     * The global scope already constrains queries, but a model resolved by an
     * explicit id (a job, a webhook) must still be re-checked.
     */
    protected function inCurrentWorkspace(Subscription $subscription): bool
    {
        return $subscription->company_id === current_company_id();
    }
}
