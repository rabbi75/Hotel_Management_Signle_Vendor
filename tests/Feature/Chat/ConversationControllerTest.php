<?php

declare(strict_types=1);

use App\Modules\Chat\Enums\ConversationType;
use App\Modules\Chat\Enums\ParticipantRole;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\Chat\Models\Message;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;

use function Pest\Laravel\get;

/**
 * Attach a user to a conversation, mirroring what ConversationService does.
 */
function joinConversation(Conversation $conversation, User $user, ParticipantRole $role = ParticipantRole::Member): void
{
    ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'role' => $role,
        'joined_at' => now(),
        'last_read_at' => null,
        'muted_at' => null,
    ]);
}

function groupConversationFor(Company $company, User ...$members): Conversation
{
    $conversation = Conversation::factory()->forCompany($company)->create();

    foreach ($members as $member) {
        joinConversation($conversation, $member);
    }

    return $conversation;
}

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('chat.index'))->assertRedirect(route('login'));
});

it('forbids a member without chat.access', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('chat.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists the conversations the viewer takes part in', function (): void {
    $company = workspace();
    $viewer = memberWith(['chat.access'], $company)->refresh();
    $other = memberWith(['chat.access'], $company)->refresh();

    $mine = groupConversationFor($company, $viewer, $other);
    $mine->update(['name' => 'Design review']);

    $theirs = groupConversationFor($company, $other);
    $theirs->update(['name' => 'Secret plans']);

    $response = actingAsMember($viewer, $company)
        ->get(route('chat.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'chat/index');

    $names = collect($response->json('props.conversations'))->pluck('name')->all();

    expect($names)->toContain('Design review')
        ->and($names)->not->toContain('Secret plans');
});

it('never shows a conversation from another workspace', function (): void {
    $company = workspace();
    $viewer = memberWith(['chat.access'], $company)->refresh();

    $other = workspace();
    $stranger = memberWith(['chat.access'], $other)->refresh();
    $foreign = groupConversationFor($other, $stranger);
    $foreign->update(['name' => 'Other tenant room']);

    // Deliberately a participant in both, so only the tenant scope can exclude it.
    joinConversation($foreign, $viewer);

    $response = actingAsMember($viewer, $company)
        ->get(route('chat.index'), inertiaHeaders())
        ->assertOk();

    $names = collect($response->json('props.conversations'))->pluck('name')->all();

    expect($names)->not->toContain('Other tenant room');
});

it('creates a group conversation', function (): void {
    $company = workspace();
    $creator = memberWith(['chat.access', 'chat.conversations.create'], $company)->refresh();
    $invitee = memberWith(['chat.access'], $company)->refresh();

    actingAsMember($creator, $company)
        ->post(route('chat.conversations.store'), [
            'type' => ConversationType::Group->value,
            'name' => 'Launch plan',
            'participant_ids' => [$invitee->id],
        ])
        ->assertRedirect();

    $conversation = Conversation::query()->where('name', 'Launch plan')->firstOrFail();

    expect($conversation->company_id)->toBe($company->id)
        ->and($conversation->participants()->count())->toBe(2);
});

it('rejects a group conversation with no name', function (): void {
    $company = workspace();
    $creator = memberWith(['chat.access', 'chat.conversations.create'], $company)->refresh();
    $invitee = memberWith(['chat.access'], $company)->refresh();

    actingAsMember($creator, $company)
        ->post(route('chat.conversations.store'), [
            'type' => ConversationType::Group->value,
            'name' => '',
            'participant_ids' => [$invitee->id],
        ])
        ->assertSessionHasErrors('name');
});

it('forbids creating a conversation without the create permission', function (): void {
    $company = workspace();
    $member = memberWith(['chat.access'], $company)->refresh();
    $invitee = memberWith(['chat.access'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('chat.conversations.store'), [
            'type' => ConversationType::Group->value,
            'name' => 'Nope',
            'participant_ids' => [$invitee->id],
        ])
        ->assertForbidden();
});

it('returns the same direct conversation when one is opened twice', function (): void {
    $company = workspace();
    $creator = memberWith(['chat.access', 'chat.conversations.create'], $company)->refresh();
    $other = memberWith(['chat.access'], $company)->refresh();

    $payload = [
        'type' => ConversationType::Direct->value,
        'participant_ids' => [$other->id],
    ];

    actingAsMember($creator, $company)->post(route('chat.conversations.store'), $payload)->assertRedirect();
    actingAsMember($creator, $company)->post(route('chat.conversations.store'), $payload)->assertRedirect();

    // And once more from the other side: the canonical key is order-independent.
    actingAsMember($other, $company)
        ->post(route('chat.conversations.store'), [
            'type' => ConversationType::Direct->value,
            'participant_ids' => [$creator->id],
        ]);

    expect(Conversation::query()->where('type', ConversationType::Direct)->count())->toBe(1);
});

it('forbids a non-participant from opening a conversation', function (): void {
    $company = workspace();
    $insider = memberWith(['chat.access'], $company)->refresh();
    $outsider = memberWith(['chat.access'], $company)->refresh();

    $conversation = groupConversationFor($company, $insider);
    Message::factory()->inConversation($conversation)->from($insider)->create(['body' => 'private']);

    $response = actingAsMember($outsider, $company)
        ->get(route('chat.index', ['conversation' => $conversation->id]), inertiaHeaders())
        ->assertOk();

    // The room is simply not selected — an outsider learns nothing about it.
    expect($response->json('props.selected'))->toBeNull();

    actingAsMember($outsider, $company)
        ->getJson(route('chat.conversations.messages.index', $conversation))
        ->assertForbidden();
});

it('marks a conversation read when it is opened', function (): void {
    $company = workspace();
    $viewer = memberWith(['chat.access'], $company)->refresh();
    $author = memberWith(['chat.access'], $company)->refresh();

    $conversation = groupConversationFor($company, $viewer, $author);
    Message::factory()->inConversation($conversation)->from($author)->create();

    actingAsMember($viewer, $company)
        ->get(route('chat.index', ['conversation' => $conversation->id]), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.selected.id', $conversation->id);

    $participant = $conversation->participantFor($viewer->id);

    expect($participant?->last_read_at)->not->toBeNull();
});
