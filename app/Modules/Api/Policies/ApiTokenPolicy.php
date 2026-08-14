<?php

declare(strict_types=1);

namespace App\Modules\Api\Policies;

use App\Modules\Api\Models\ApiToken;
use App\Modules\User\Models\User;

class ApiTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('api.tokens.view');
    }

    public function view(User $user, ApiToken $token): bool
    {
        return $this->inCurrentWorkspace($token) && $user->can('api.tokens.view');
    }

    public function create(User $user): bool
    {
        return $user->can('api.tokens.create');
    }

    public function revoke(User $user, ApiToken $token): bool
    {
        return $this->inCurrentWorkspace($token) && $user->can('api.tokens.revoke');
    }

    public function delete(User $user, ApiToken $token): bool
    {
        return $this->revoke($user, $token);
    }

    /**
     * Tokens carry no global scope (Sanctum resolves them before a tenant
     * exists), so the workspace check has to be explicit here.
     */
    protected function inCurrentWorkspace(ApiToken $token): bool
    {
        return $token->company_id === current_company_id();
    }
}
