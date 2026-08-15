<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Admin;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Services\TicketService;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformTicketController extends Controller
{
    public function __construct(protected TicketService $tickets) {}

    public function index(Request $request): Response
    {
        abort_if($request->user('admin')?->cannot('platform.tickets.view') ?? true, 403);

        $query = SupportTicket::query()
            ->withoutCompanyScope()
            ->with(['company:id,uuid,name', 'requester:id,name,email', 'assignee:id,name']);

        if ($request->filled('company')) {
            $query->whereHas('company', static fn ($q) => $q->where('uuid', $request->string('company')));
        }

        $table = TableBuilder::for($query, $request, 'platform-tickets')
            ->columns([
                Column::make('number', __('Ticket'))->sortable()->searchable()->locked(),
                Column::make('subject', __('Subject'))->sortable()->searchable(),
                Column::make('company', __('Tenant'))->searchable('company.name'),
                Column::make('status', __('Status'))->sortable(),
                Column::make('priority', __('Priority'))->sortable(),
                Column::make('assignee', __('Assignee'))->searchable('assignee.name'),
                Column::make('updated_at', __('Updated'))->sortable(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(TicketStatus::class)->multiple(),
                Filter::make('priority', __('Priority'))->fromEnum(TicketPriority::class)->multiple(),
                Filter::make('category', __('Category'))->fromEnum(TicketCategory::class)->multiple(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->transform(fn (SupportTicket $ticket): array => $this->serialize($ticket));

        return Inertia::render('admin/tickets/index', [
            'table' => $table->toArray(),
            'can' => [
                'manage' => $request->user('admin')?->can('platform.tickets.manage') ?? false,
            ],
        ]);
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        abort_if($request->user('admin')?->cannot('platform.tickets.view') ?? true, 403);

        $ticket->load([
            'company:id,uuid,name',
            'requester:id,name,email',
            'assignee:id,name',
            'messages.user:id,name',
            'messages.admin:id,name',
        ]);

        $admins = Admin::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(static fn (Admin $admin): array => [(string) $admin->id => $admin->name])
            ->all();

        return Inertia::render('admin/tickets/show', [
            'ticket' => $this->serialize($ticket, withMessages: true),
            'admins' => $admins,
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
            'categories' => TicketCategory::options(),
            'can' => [
                'manage' => $request->user('admin')?->can('platform.tickets.manage') ?? false,
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.tickets.manage') ?? true, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $this->tickets->replyAsAdmin(
            $ticket,
            $request->user('admin'),
            $validated['body'],
            (bool) ($validated['is_internal'] ?? false),
        );

        return back()->with('success', __('Reply posted.'));
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.tickets.manage') ?? true, 403);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:'.implode(',', TicketStatus::values())],
            'priority' => ['sometimes', 'string', 'in:'.implode(',', TicketPriority::values())],
            'category' => ['sometimes', 'string', 'in:'.implode(',', TicketCategory::values())],
            'assigned_admin_id' => ['sometimes', 'nullable', 'integer', 'exists:admins,id'],
        ]);

        $admin = $request->user('admin');

        if (array_key_exists('assigned_admin_id', $validated)) {
            $assignee = $validated['assigned_admin_id']
                ? Admin::query()->find($validated['assigned_admin_id'])
                : null;
            $this->tickets->assign($ticket, $assignee, $admin);
        }

        if (isset($validated['status'])) {
            $this->tickets->updateStatus($ticket, TicketStatus::from($validated['status']), $admin);
        }

        $this->tickets->updateMeta(
            $ticket->fresh() ?? $ticket,
            isset($validated['priority']) ? TicketPriority::from($validated['priority']) : null,
            isset($validated['category']) ? TicketCategory::from($validated['category']) : null,
        );

        return back()->with('success', __('Ticket updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(SupportTicket $ticket, bool $withMessages = false): array
    {
        $payload = [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'status_color' => $ticket->status->color(),
            'priority' => $ticket->priority->value,
            'priority_label' => $ticket->priority->label(),
            'priority_color' => $ticket->priority->color(),
            'category' => $ticket->category->value,
            'category_label' => $ticket->category->label(),
            'company' => $ticket->company?->name,
            'company_uuid' => $ticket->company?->uuid,
            'requester' => $ticket->requester?->name,
            'requester_email' => $ticket->requester?->email,
            'assignee' => $ticket->assignee?->name,
            'assigned_admin_id' => $ticket->assigned_admin_id,
            'last_replied_at' => $ticket->last_replied_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];

        if ($withMessages) {
            $payload['messages'] = $ticket->messages->map(static fn ($message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'is_internal' => $message->is_internal,
                'author' => $message->admin?->name ?? $message->user?->name ?? __('Unknown'),
                'author_type' => $message->admin_id ? 'support' : 'customer',
                'created_at' => $message->created_at?->toIso8601String(),
            ])->all();
        }

        return $payload;
    }
}
