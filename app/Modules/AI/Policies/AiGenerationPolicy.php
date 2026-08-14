<?php

declare(strict_types=1);

namespace App\Modules\AI\Policies;

use App\Modules\AI\Models\AiGeneration;
use App\Modules\User\Models\User;

class AiGenerationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ai.history.view');
    }

    public function view(User $user, AiGeneration $generation): bool
    {
        return $generation->company_id === current_company_id() && $user->can('ai.history.view');
    }

    public function create(User $user): bool
    {
        return $user->can('ai.use');
    }

    public function delete(User $user, AiGeneration $generation): bool
    {
        return $generation->company_id === current_company_id()
            && ($generation->user_id === $user->id || $user->can('ai.credits.manage'));
    }
}
