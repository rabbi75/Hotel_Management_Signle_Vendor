<?php

declare(strict_types=1);

use App\Modules\Chat\Models\Conversation;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Support\Facades\Broadcast;

/*
|------------------------------------------------------------------------------
| Broadcast channels
|------------------------------------------------------------------------------
|
| Every channel here is private or presence: nothing in this application is
| safe to broadcast publicly, since even a workspace name leaks tenant data.
|
*/

// Per-user channel — notifications, personal events.
Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id): bool => $user->id === $id);

// Per-workspace channel — dashboard updates, shared activity feeds. Membership
// is re-checked here rather than trusting the session's active workspace.
Broadcast::channel('company.{companyId}', fn (User $user, int $companyId): bool => $user->belongsToCompany($companyId));

// Presence channel backing "who is online" indicators.
Broadcast::channel('presence.company.{companyId}', function (User $user, int $companyId): array|bool {
    if (! $user->belongsToCompany($companyId)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => $user->avatarUrl(),
        'initials' => $user->initials(),
    ];
});

// Per-conversation channel — messages, edits, deletions, typing and read
// receipts. Authorised by participation rather than by workspace membership:
// belonging to the workspace does not entitle anyone to read a private room.
// The tenant scope is bypassed because broadcasting authorisation runs without
// the session that would otherwise have resolved the workspace.
Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId): bool {
    $conversation = Conversation::query()->withoutGlobalScope(CompanyScope::class)->find($conversationId);

    return $conversation instanceof Conversation
        && $user->belongsToCompany($conversation->company_id)
        && $conversation->hasParticipant($user->id);
});
