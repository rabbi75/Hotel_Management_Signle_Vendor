<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chat\Events\UserTyping;
use App\Modules\Chat\Models\Conversation;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Typing is deliberately fire-and-forget: nothing is persisted, and the
 * indicator expires on the client after `saas.chat.typing_ttl` seconds even if
 * the "stopped" signal never arrives.
 */
class TypingController extends Controller
{
    public function __invoke(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('post', $conversation);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        UserTyping::dispatch(
            $conversation->id,
            $user->id,
            $user->name,
            $request->boolean('typing', true),
        );

        return response()->json(['ttl' => (int) config('saas.chat.typing_ttl')]);
    }
}
