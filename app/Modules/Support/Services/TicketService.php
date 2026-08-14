<?php

declare(strict_types=1);

namespace App\Modules\Support\Services;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Platform\Models\Admin;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Modules\Support\Notifications\TicketCreatedNotification;
use App\Modules\Support\Notifications\TicketRepliedNotification;
use App\Modules\Support\Notifications\TicketStatusChangedNotification;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

class TicketService
{
    public function __construct(protected SecurityLogger $security) {}

    /**
     * @param  array{subject: string, body: string, priority?: string, category?: string}  $data
     */
    public function open(User $user, array $data): SupportTicket
    {
        return DB::transaction(function () use ($user, $data): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'user_id' => $user->id,
                'subject' => $data['subject'],
                'status' => TicketStatus::AwaitingSupport,
                'priority' => TicketPriority::tryFrom($data['priority'] ?? '') ?? TicketPriority::Normal,
                'category' => TicketCategory::tryFrom($data['category'] ?? '') ?? TicketCategory::Other,
                'last_replied_at' => now(),
            ]);

            SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'body' => $data['body'],
                'is_internal' => false,
            ]);

            $this->security->log(
                SecurityEvent::SupportTicketCreated,
                $user,
                __('Opened support ticket :number.', ['number' => $ticket->fresh()->number]),
                ['company_id' => $ticket->company_id, 'ticket_id' => $ticket->id],
            );

            $this->notifyAdmins(new TicketCreatedNotification($ticket->fresh(['company', 'requester'])));

            return $ticket->fresh(['messages', 'requester', 'company']) ?? $ticket;
        });
    }

    public function replyAsUser(SupportTicket $ticket, User $user, string $body): SupportTicketMessage
    {
        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $body,
            'is_internal' => false,
        ]);

        $ticket->forceFill([
            'status' => TicketStatus::AwaitingSupport,
            'last_replied_at' => now(),
        ])->save();

        $this->security->log(
            SecurityEvent::SupportTicketReplied,
            $user,
            __('Replied to support ticket :number.', ['number' => $ticket->number]),
            ['company_id' => $ticket->company_id, 'ticket_id' => $ticket->id, 'message_id' => $message->id],
        );

        $this->notifyAdminRecipients($ticket, new TicketRepliedNotification($ticket, $message, fromAdmin: false));

        return $message;
    }

    public function replyAsAdmin(SupportTicket $ticket, Admin $admin, string $body, bool $internal = false): SupportTicketMessage
    {
        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'admin_id' => $admin->id,
            'body' => $body,
            'is_internal' => $internal,
        ]);

        if (! $internal) {
            $ticket->forceFill([
                'status' => TicketStatus::AwaitingCustomer,
                'last_replied_at' => now(),
                'assigned_admin_id' => $ticket->assigned_admin_id ?? $admin->id,
            ])->save();
        }

        $this->security->log(
            SecurityEvent::SupportTicketReplied,
            $admin,
            __('Replied to support ticket :number.', ['number' => $ticket->number]),
            [
                'company_id' => $ticket->company_id,
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'internal' => $internal,
            ],
        );

        if (! $internal) {
            $ticket->requester?->notify(new TicketRepliedNotification($ticket, $message, fromAdmin: true));
        }

        return $message;
    }

    public function updateStatus(SupportTicket $ticket, TicketStatus $status, Admin|User $actor): SupportTicket
    {
        $previous = $ticket->status;

        $ticket->forceFill([
            'status' => $status,
            'resolved_at' => $status === TicketStatus::Resolved ? now() : $ticket->resolved_at,
            'closed_at' => $status === TicketStatus::Closed ? now() : ($status->isOpen() ? null : $ticket->closed_at),
        ])->save();

        if ($status === TicketStatus::AwaitingSupport && in_array($previous, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            $ticket->forceFill(['resolved_at' => null, 'closed_at' => null])->save();
        }

        $this->security->log(
            SecurityEvent::SupportTicketStatusChanged,
            $actor,
            __('Changed support ticket :number to :status.', [
                'number' => $ticket->number,
                'status' => $status->label(),
            ]),
            [
                'company_id' => $ticket->company_id,
                'ticket_id' => $ticket->id,
                'from' => $previous->value,
                'to' => $status->value,
            ],
        );

        if (in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true) && $ticket->requester) {
            $ticket->requester->notify(new TicketStatusChangedNotification($ticket, $status));
        }

        return $ticket;
    }

    public function assign(SupportTicket $ticket, ?Admin $admin, Admin $actor): SupportTicket
    {
        $ticket->forceFill(['assigned_admin_id' => $admin?->id])->save();

        $this->security->log(
            SecurityEvent::SupportTicketAssigned,
            $actor,
            $admin
                ? __('Assigned support ticket :number to :admin.', ['number' => $ticket->number, 'admin' => $admin->name])
                : __('Unassigned support ticket :number.', ['number' => $ticket->number]),
            [
                'company_id' => $ticket->company_id,
                'ticket_id' => $ticket->id,
                'assigned_admin_id' => $admin?->id,
            ],
        );

        return $ticket;
    }

    public function updateMeta(SupportTicket $ticket, ?TicketPriority $priority, ?TicketCategory $category): SupportTicket
    {
        $ticket->forceFill(array_filter([
            'priority' => $priority,
            'category' => $category,
        ], static fn (mixed $value): bool => $value !== null))->save();

        return $ticket;
    }

    protected function notifyAdmins(object $notification): void
    {
        if (! Permission::query()->where('name', 'platform.tickets.view')->where('guard_name', 'admin')->exists()) {
            return;
        }

        $admins = Admin::query()
            ->permission('platform.tickets.view')
            ->where('status', 'active')
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, $notification);
        }
    }

    protected function notifyAdminRecipients(SupportTicket $ticket, object $notification): void
    {
        if ($ticket->assignee) {
            $ticket->assignee->notify($notification);

            return;
        }

        $this->notifyAdmins($notification);
    }
}
