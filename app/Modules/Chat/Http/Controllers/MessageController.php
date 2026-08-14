<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chat\DTOs\MessageData;
use App\Modules\Chat\Http\Requests\StoreMessageRequest;
use App\Modules\Chat\Http\Requests\UpdateMessageRequest;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\AttachmentService;
use App\Modules\Chat\Services\MessageService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct(
        protected MessageService $messages,
        protected AttachmentService $attachments,
    ) {}

    /**
     * A page of thread history, newest first.
     *
     * Answered as JSON rather than as an Inertia partial: the thread is
     * virtualised and prepends pages as the user scrolls up, which is a data
     * fetch, not a navigation.
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $page = $this->messages->history($conversation, $request->string('cursor')->toString() ?: null);

        return response()->json([
            'data' => array_values(array_map(
                fn (Message $message): array => $this->messages->payload($message),
                $page->items(),
            )),
            'next_cursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $files = $request->attachments();

        if ($files !== []) {
            Gate::authorize('uploadAttachment', $conversation);
        }

        $message = $this->messages->send($conversation, $user, MessageData::fromRequest($request));

        if ($files !== []) {
            $this->attachments->attach($message, $files);

            // The attachments landed after the send broadcast, so the room is
            // told again with the complete message rather than a bare body.
            $this->messages->broadcastUpdate($message->fresh() ?? $message);
        }

        return back();
    }

    public function update(UpdateMessageRequest $request, Message $message): RedirectResponse
    {
        $this->messages->edit($message, (string) $request->string('body'));

        return back();
    }

    public function destroy(Message $message): RedirectResponse
    {
        Gate::authorize('delete', $message);

        $this->messages->delete($message);

        return back();
    }
}
