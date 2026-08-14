<?php

declare(strict_types=1);

namespace App\Modules\Support\Policies;

use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\User\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('support.view');
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $this->inCurrentCompany($ticket) && $user->can('support.view');
    }

    public function create(User $user): bool
    {
        return $user->can('support.create');
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $this->inCurrentCompany($ticket)
            && $user->can('support.create')
            && $ticket->status !== TicketStatus::Closed;
    }

    public function close(User $user, SupportTicket $ticket): bool
    {
        return $this->inCurrentCompany($ticket)
            && $user->can('support.create')
            && in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::AwaitingCustomer, TicketStatus::AwaitingSupport], true);
    }

    protected function inCurrentCompany(SupportTicket $ticket): bool
    {
        return $ticket->company_id === current_company_id();
    }
}
