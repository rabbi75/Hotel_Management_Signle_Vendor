<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chat\Http\Requests\SearchMessagesRequest;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\MessageService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SearchController extends Controller
{
    public function __construct(protected MessageService $messages) {}

    /**
     * Search within one conversation, or across every conversation the user
     * takes part in when no id is supplied.
     */
    public function __invoke(SearchMessagesRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $conversation = null;
        $id = $request->integer('conversation_id');

        if ($id > 0) {
            $conversation = Conversation::query()->findOrFail($id);
            Gate::authorize('view', $conversation);
        }

        $page = $this->messages->search(
            $user,
            $request->term(),
            $conversation,
            $request->string('cursor')->toString() ?: null,
        );

        return response()->json([
            'data' => array_values(array_map(
                fn (Message $message): array => [
                    ...$this->messages->payload($message),
                    'conversation_title' => $message->conversation?->titleFor($user->id),
                ],
                $page->items(),
            )),
            'next_cursor' => $page->nextCursor()?->encode(),
        ]);
    }
}
