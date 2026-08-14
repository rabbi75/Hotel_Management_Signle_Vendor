<?php

declare(strict_types=1);

namespace App\Modules\AI\Policies;

use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\User\Models\User;

class AiCreditBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ai.use');
    }

    public function view(User $user, AiCreditBalance $balance): bool
    {
        return $balance->company_id === current_company_id() && $user->can('ai.use');
    }

    public function manage(User $user): bool
    {
        return $user->can('ai.credits.manage');
    }
}
