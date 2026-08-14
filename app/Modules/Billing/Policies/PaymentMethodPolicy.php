<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Modules\Billing\Models\PaymentMethod;
use App\Modules\User\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.payment_methods.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('billing.payment_methods.manage');
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->owns($paymentMethod) && $user->can('billing.payment_methods.manage');
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->update($user, $paymentMethod);
    }

    protected function owns(PaymentMethod $paymentMethod): bool
    {
        return $paymentMethod->company_id === current_company_id();
    }
}
