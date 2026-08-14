<?php

declare(strict_types=1);

namespace App\Modules\Chat\Policies;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\User\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return $user->can('chat.access')
            && $message->company_id === current_company_id()
            && $this->isParticipant($user, $message);
    }

    /**
     * Only the author may edit, and only inside the configured window. A
     * moderator's `delete_any` deliberately does not extend to editing:
     * rewriting someone else's words is never an option.
     */
    public function update(User $user, Message $message): bool
    {
        return $this->view($user, $message)
            && $message->isAuthoredBy($user->id)
            && $message->type->isEditable()
            && $message->isWithinEditWindow();
    }

    public function delete(User $user, Message $message): bool
    {
        if (! $this->view($user, $message)) {
            return false;
        }

        return $message->isAuthoredBy($user->id) || $user->can('chat.messages.delete_any');
    }

    public function react(User $user, Message $message): bool
    {
        return $this->view($user, $message);
    }

    protected function isParticipant(User $user, Message $message): bool
    {
        $conversation = $message->relationLoaded('conversation')
            ? $message->conversation
            : $message->conversation()->first();

        return $conversation instanceof Conversation && $conversation->hasParticipant($user->id);
    }
}
