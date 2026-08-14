<?php

declare(strict_types=1);

namespace App\Modules\Chat\Policies;

use App\Modules\Chat\Enums\ConversationType;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\User\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('chat.access');
    }

    /**
     * Reading a room requires both the module permission and membership of
     * that specific room. Neither alone is enough.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->can('chat.access')
            && $this->inCurrentWorkspace($conversation)
            && $conversation->hasParticipant($user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('chat.conversations.create');
    }

    public function post(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    /**
     * Renaming a room, or changing who is in it.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        if (! $this->view($user, $conversation)) {
            return false;
        }

        // A direct conversation has no settings and a fixed pair; there is
        // nothing about it that anyone is allowed to change.
        if ($conversation->type === ConversationType::Direct) {
            return false;
        }

        $participant = $conversation->participantFor($user->id);

        return $participant instanceof ConversationParticipant
            && $participant->role->canManageParticipants();
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->update($user, $conversation);
    }

    public function uploadAttachment(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation) && $user->can('chat.attachments.upload');
    }

    protected function inCurrentWorkspace(Conversation $conversation): bool
    {
        return $conversation->company_id === current_company_id();
    }
}
