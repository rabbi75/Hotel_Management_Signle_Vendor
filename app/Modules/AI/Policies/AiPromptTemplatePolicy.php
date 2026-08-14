<?php

declare(strict_types=1);

namespace App\Modules\AI\Policies;

use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\User\Models\User;

class AiPromptTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ai.use');
    }

    public function view(User $user, AiPromptTemplate $template): bool
    {
        if (! $this->inCurrentWorkspace($template)) {
            return false;
        }

        // A private template belongs to its author; sharing is what makes it
        // visible to the rest of the workspace.
        return $user->can('ai.use') && ($template->is_shared || $template->user_id === $user->id || $user->can('ai.templates.manage'));
    }

    public function create(User $user): bool
    {
        return $user->can('ai.templates.manage');
    }

    public function update(User $user, AiPromptTemplate $template): bool
    {
        return $this->inCurrentWorkspace($template) && $user->can('ai.templates.manage');
    }

    public function delete(User $user, AiPromptTemplate $template): bool
    {
        return $this->update($user, $template);
    }

    protected function inCurrentWorkspace(AiPromptTemplate $template): bool
    {
        return $template->company_id === current_company_id();
    }
}
