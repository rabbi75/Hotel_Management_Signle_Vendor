<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Modules\Billing\Models\Coupon;
use App\Modules\User\Models\User;

class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.coupons.manage');
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('billing.coupons.manage');
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $this->create($user);
    }

    /**
     * Redeeming is a customer action, not an administrative one: anyone who may
     * change the subscription may apply a code to it.
     */
    public function redeem(User $user): bool
    {
        return $user->can('billing.subscribe');
    }
}
