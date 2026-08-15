<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
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
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function __construct(protected TicketService $tickets) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SupportTicket::class);

        $table = TableBuilder::for(SupportTicket::query()->with(['requester:id,name']), $request, 'support-tickets')
            ->columns([
                Column::make('number', __('Ticket'))->sortable()->searchable()->locked(),
                Column::make('subject', __('Subject'))->sortable()->searchable(),
                Column::make('status', __('Status'))->sortable(),
                Column::make('priority', __('Priority'))->sortable(),
                Column::make('category', __('Category'))->sortable(),
                Column::make('updated_at', __('Updated'))->sortable(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(TicketStatus::class)->multiple(),
                Filter::make('priority', __('Priority'))->fromEnum(TicketPriority::class)->multiple(),
                Filter::make('category', __('Category'))->fromEnum(TicketCategory::class)->multiple(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->transform(fn (SupportTicket $ticket): array => $this->serializeTicket($ticket));

        return Inertia::render('support/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', SupportTicket::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', SupportTicket::class);

        return Inertia::render('support/create', [
            'priorities' => TicketPriority::options(),
            'categories' => TicketCategory::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', SupportTicket::class);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'priority' => ['required', 'string', 'in:'.implode(',', TicketPriority::values())],
            'category' => ['required', 'string', 'in:'.implode(',', TicketCategory::values())],
        ]);

        $ticket = $this->tickets->open($request->user(), $validated);

        return redirect()
            ->route('support.show', $ticket)
            ->with('success', __('Ticket :number opened.', ['number' => $ticket->number]));
    }

    public function show(SupportTicket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'requester:id,name,email',
            'messages' => static fn ($query) => $query->where('is_internal', false)->with(['user:id,name', 'admin:id,name']),
        ]);

        return Inertia::render('support/show', [
            'ticket' => $this->serializeTicket($ticket, withMessages: true),
            'can' => [
                'reply' => Gate::allows('reply', $ticket),
                'close' => Gate::allows('close', $ticket),
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('reply', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $this->tickets->replyAsUser($ticket, $request->user(), $validated['body']);

        return back()->with('success', __('Reply sent.'));
    }

    public function close(Request $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('close', $ticket);

        $this->tickets->updateStatus($ticket, TicketStatus::Closed, $request->user());

        return back()->with('success', __('Ticket closed.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTicket(SupportTicket $ticket, bool $withMessages = false): array
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
            'requester' => $ticket->requester?->name,
            'last_replied_at' => $ticket->last_replied_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];

        if ($withMessages) {
            $payload['messages'] = $ticket->messages->map(static fn ($message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'is_internal' => false,
                'author' => $message->admin?->name ?? $message->user?->name ?? __('Unknown'),
                'author_type' => $message->admin_id ? 'support' : 'customer',
                'created_at' => $message->created_at?->toIso8601String(),
            ])->all();
        }

        return $payload;
    }
}
