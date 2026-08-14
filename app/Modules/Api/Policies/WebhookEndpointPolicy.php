<?php

declare(strict_types=1);

namespace App\Modules\Api\Policies;

use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\User\Models\User;

class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('api.webhooks.manage');
    }

    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->inCurrentWorkspace($endpoint) && $user->can('api.webhooks.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('api.webhooks.manage');
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->view($user, $endpoint);
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->view($user, $endpoint);
    }

    protected function inCurrentWorkspace(WebhookEndpoint $endpoint): bool
    {
        return $endpoint->company_id === current_company_id();
    }
}
