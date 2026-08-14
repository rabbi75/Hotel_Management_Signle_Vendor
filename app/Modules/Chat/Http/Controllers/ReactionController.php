<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chat\Http\Requests\StoreReactionRequest;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Models\MessageReaction;
use App\Modules\Chat\Services\MessageService;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;

class ReactionController extends Controller
{
    public function __construct(protected MessageService $messages) {}

    /**
     * Toggling rather than adding: tapping the same emoji twice is how every
     * chat client on earth removes a reaction, so a separate delete route would
     * only ever be called by mistake.
     */
    public function store(StoreReactionRequest $request, Message $message): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $emoji = (string) $request->string('emoji');

        $existing = MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing instanceof MessageReaction) {
            $existing->delete();
        } else {
            MessageReaction::query()->create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $emoji,
            ]);
        }

        $this->messages->broadcastUpdate($message->fresh() ?? $message);

        return back();
    }
}
