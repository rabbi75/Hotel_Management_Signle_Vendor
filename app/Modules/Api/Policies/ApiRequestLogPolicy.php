<?php

declare(strict_types=1);

namespace App\Modules\Api\Policies;

use App\Modules\Api\Models\ApiRequestLog;
use App\Modules\User\Models\User;

class ApiRequestLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('api.logs.view');
    }

    public function view(User $user, ApiRequestLog $log): bool
    {
        return $log->company_id === current_company_id() && $user->can('api.logs.view');
    }
}
