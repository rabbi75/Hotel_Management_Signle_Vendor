<?php

declare(strict_types=1);

use App\Modules\Chat\Enums\ParticipantRole;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\Chat\Models\Message;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;

use function Pest\Laravel\post;

function joinRoom(Conversation $conversation, User $user, ParticipantRole $role = ParticipantRole::Member): void
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

function roomFor(Company $company, User ...$members): Conversation
{
    $conversation = Conversation::factory()->forCompany($company)->create();

    foreach ($members as $member) {
        joinRoom($conversation, $member);
    }

    return $conversation;
}

it('redirects a guest posting a message', function (): void {
    $company = workspace();
    $member = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $member);

    post(route('chat.conversations.messages.store', $conversation), ['body' => 'hello'])
        ->assertRedirect(route('login'));
});

it('sends a message to a conversation the sender belongs to', function (): void {
    $company = workspace();
    $sender = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $sender);

    actingAsMember($sender, $company)
        ->post(route('chat.conversations.messages.store', $conversation), ['body' => 'Morning all'])
        ->assertRedirect();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'body' => 'Morning all',
        'company_id' => $company->id,
    ]);
});

it('forbids a non-participant from posting', function (): void {
    $company = workspace();
    $insider = memberWith(['chat.access'], $company)->refresh();
    $outsider = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $insider);

    actingAsMember($outsider, $company)
        ->post(route('chat.conversations.messages.store', $conversation), ['body' => 'let me in'])
        ->assertForbidden();
});

it('rejects an empty message', function (): void {
    $company = workspace();
    $sender = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $sender);

    actingAsMember($sender, $company)
        ->post(route('chat.conversations.messages.store', $conversation), ['body' => ''])
        ->assertSessionHasErrors('body');
});

it('rejects a message longer than the configured limit', function (): void {
    $company = workspace();
    $sender = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $sender);

    $tooLong = str_repeat('a', (int) config('saas.chat.max_message_length') + 1);

    actingAsMember($sender, $company)
        ->post(route('chat.conversations.messages.store', $conversation), ['body' => $tooLong])
        ->assertSessionHasErrors('body');

    expect(Message::query()->count())->toBe(0);
});

it('lets the author edit inside the edit window', function (): void {
    $company = workspace();
    $sender = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $sender);
    $message = Message::factory()->inConversation($conversation)->from($sender)->create(['body' => 'typo']);

    actingAsMember($sender, $company)
        ->patch(route('chat.messages.update', $message), ['body' => 'fixed'])
        ->assertRedirect();

    expect($message->fresh()?->body)->toBe('fixed')
        ->and($message->fresh()?->edited_at)->not->toBeNull();
});

it('refuses an edit made outside the window', function (): void {
    $company = workspace();
    $sender = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $sender);

    $minutes = (int) config('saas.chat.edit_window_minutes');
    $message = Message::factory()->inConversation($conversation)->from($sender)->create([
        'created_at' => now()->subMinutes($minutes + 1),
    ]);

    actingAsMember($sender, $company)
        ->patch(route('chat.messages.update', $message), ['body' => 'too late'])
        ->assertForbidden();
});

it('refuses an edit by someone who did not write the message', function (): void {
    $company = workspace();
    $author = memberWith(['chat.access'], $company)->refresh();
    $moderator = memberWith(['chat.access', 'chat.messages.delete_any'], $company)->refresh();
    $conversation = roomFor($company, $author, $moderator);
    $message = Message::factory()->inConversation($conversation)->from($author)->create();

    actingAsMember($moderator, $company)
        ->patch(route('chat.messages.update', $message), ['body' => 'rewritten'])
        ->assertForbidden();
});

it('lets the author delete their own message', function (): void {
    $company = workspace();
    $author = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $author);
    $message = Message::factory()->inConversation($conversation)->from($author)->create();

    actingAsMember($author, $company)
        ->delete(route('chat.messages.destroy', $message))
        ->assertRedirect();

    $this->assertSoftDeleted('messages', ['id' => $message->id]);
});

it('lets a moderator delete anyone else\'s message', function (): void {
    $company = workspace();
    $author = memberWith(['chat.access'], $company)->refresh();
    $moderator = memberWith(['chat.access', 'chat.messages.delete_any'], $company)->refresh();
    $conversation = roomFor($company, $author, $moderator);
    $message = Message::factory()->inConversation($conversation)->from($author)->create();

    actingAsMember($moderator, $company)
        ->delete(route('chat.messages.destroy', $message))
        ->assertRedirect();

    $this->assertSoftDeleted('messages', ['id' => $message->id]);
});

it('refuses a plain member deleting someone else\'s message', function (): void {
    $company = workspace();
    $author = memberWith(['chat.access'], $company)->refresh();
    $bystander = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $author, $bystander);
    $message = Message::factory()->inConversation($conversation)->from($author)->create();

    actingAsMember($bystander, $company)
        ->delete(route('chat.messages.destroy', $message))
        ->assertForbidden();
});

it('paginates history with a cursor', function (): void {
    $company = workspace();
    $viewer = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $viewer);

    $perPage = (int) config('saas.chat.messages_per_page');
    Message::factory()->count($perPage + 5)->inConversation($conversation)->from($viewer)->create();

    $response = actingAsMember($viewer, $company)
        ->getJson(route('chat.conversations.messages.index', $conversation))
        ->assertOk();

    expect($response->json('data'))->toHaveCount($perPage)
        ->and($response->json('next_cursor'))->not->toBeNull();
});

it('toggles a reaction on and off', function (): void {
    $company = workspace();
    $viewer = memberWith(['chat.access'], $company)->refresh();
    $conversation = roomFor($company, $viewer);
    $message = Message::factory()->inConversation($conversation)->from($viewer)->create();

    actingAsMember($viewer, $company)
        ->post(route('chat.messages.reactions.store', $message), ['emoji' => '👍'])
        ->assertRedirect();

    $this->assertDatabaseCount('message_reactions', 1);

    actingAsMember($viewer, $company)
        ->post(route('chat.messages.reactions.store', $message), ['emoji' => '👍']);

    $this->assertDatabaseCount('message_reactions', 0);
});

it('searches messages only in rooms the viewer belongs to', function (): void {
    $company = workspace();
    $viewer = memberWith(['chat.access'], $company)->refresh();
    $stranger = memberWith(['chat.access'], $company)->refresh();

    $mine = roomFor($company, $viewer);
    $theirs = roomFor($company, $stranger);

    Message::factory()->inConversation($mine)->from($viewer)->create(['body' => 'quarterly budget review']);
    Message::factory()->inConversation($theirs)->from($stranger)->create(['body' => 'quarterly secret memo']);

    $response = actingAsMember($viewer, $company)
        ->getJson(route('chat.search', ['q' => 'quarterly']))
        ->assertOk();

    $bodies = collect($response->json('data'))->pluck('body')->all();

    expect($bodies)->toContain('quarterly budget review')
        ->and($bodies)->not->toContain('quarterly secret memo');
});
