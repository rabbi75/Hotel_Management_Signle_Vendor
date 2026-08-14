<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chat\Enums\ConversationType;
use App\Modules\Chat\Http\Requests\StoreConversationRequest;
use App\Modules\Chat\Http\Requests\UpdateConversationRequest;
use App\Modules\Chat\Http\Resources\ConversationResource;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Chat\Services\MessageService;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversations,
        protected MessageService $messages,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Conversation::class);

        $user = $this->user($request);

        $search = $request->string('search')->toString() ?: null;
        $conversations = $this->conversations->listFor($user, $search);
        $unread = $this->conversations->unreadCounts($user);

        $selected = $this->resolveSelected($request, $user);

        return Inertia::render('chat/index', [
            'conversations' => array_values(array_map(
                fn (Conversation $conversation): array => (new ConversationResource($conversation))
                    ->withUnread($unread, $user->id)
                    ->resolve($request),
                $conversations->all(),
            )),
            'selected' => $selected === null
                ? null
                : (new ConversationResource($selected))->withUnread($unread, $user->id)->resolve($request),
            'messages' => $selected === null ? null : $this->initialMessages($selected),
            'members' => $this->memberOptions($user),
            'search' => $search,
            'limits' => [
                'max_message_length' => (int) config('saas.chat.max_message_length'),
                'max_attachment_kb' => (int) config('saas.chat.max_attachment_kb'),
                'typing_ttl' => (int) config('saas.chat.typing_ttl'),
                'edit_window_minutes' => (int) config('saas.chat.edit_window_minutes'),
                'per_page' => (int) config('saas.chat.messages_per_page'),
            ],
            'can' => [
                'create' => Gate::allows('create', Conversation::class),
                'upload' => $user->can('chat.attachments.upload'),
                'delete_any' => $user->can('chat.messages.delete_any'),
            ],
        ]);
    }

    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $ids = $request->participantIds();
        $type = $request->conversationType();

        if ($type === ConversationType::Direct) {
            $other = User::query()->findOrFail($ids[0] ?? 0);
            $conversation = $this->conversations->findOrCreateDirect($user, $other);
        } else {
            $conversation = $this->conversations->createGroup(
                $user,
                (string) $request->string('name'),
                $request->string('description')->toString() ?: null,
                $ids,
                $type,
            );
        }

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): RedirectResponse
    {
        if ($request->has('name')) {
            $conversation->name = (string) $request->string('name');
        }

        // `has` rather than `filled`: an explicitly submitted null clears the
        // description, while omitting the key must leave it untouched.
        if ($request->has('description')) {
            $conversation->description = $request->string('description')->toString() ?: null;
        }

        $conversation->save();

        if ($request->has('participant_ids')) {
            /** @var array<int, mixed> $ids */
            $ids = (array) $request->input('participant_ids', []);
            $this->conversations->addParticipants($conversation, array_values(array_map(intval(...), $ids)));
        }

        return back()->with('success', __('Conversation updated.'));
    }

    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('delete', $conversation);

        $conversation->delete();

        return redirect()->route('chat.index')->with('success', __('Conversation deleted.'));
    }

    /**
     * Leave a conversation, or remove someone else from it.
     */
    public function leave(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation);

        $this->conversations->removeParticipant($conversation, $this->user($request)->id);

        return redirect()->route('chat.index')->with('success', __('You left the conversation.'));
    }

    public function markRead(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation);

        $this->conversations->markRead($conversation, $this->user($request));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function initialMessages(Conversation $conversation): array
    {
        $page = $this->messages->history($conversation);

        return [
            'data' => array_values(array_map(
                fn ($message): array => $this->messages->payload($message),
                $page->items(),
            )),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    protected function resolveSelected(Request $request, User $user): ?Conversation
    {
        $id = $request->integer('conversation');

        if ($id <= 0) {
            return null;
        }

        $conversation = Conversation::query()
            ->with(['participants.user'])
            ->find($id);

        if (! $conversation instanceof Conversation || ! Gate::forUser($user)->allows('view', $conversation)) {
            return null;
        }

        $this->conversations->markRead($conversation, $user);

        return $conversation;
    }

    /**
     * Workspace members this user could start a conversation with.
     *
     * @return list<array<string, mixed>>
     */
    protected function memberOptions(User $user): array
    {
        $company = current_company();

        if ($company === null) {
            return [];
        }

        return array_values(array_map(
            static fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'initials' => $member->initials(),
                'avatar' => $member->avatarUrl(),
                'job_title' => $member->job_title,
            ],
            $company->members()->where('users.id', '!=', $user->id)->orderBy('users.name')->get()->all(),
        ));
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
