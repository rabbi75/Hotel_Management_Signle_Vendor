<?php

declare(strict_types=1);

use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Services\ApiTokenService;
use App\Modules\User\Models\User;

it('rejects an unauthenticated visitor', function (): void {
    $this->get(route('api.tokens.index'))->assertRedirect(route('login'));
});

it('forbids a member without the view permission', function (): void {
    $company = workspace();
    $user = memberWith([], $company);

    actingAsMember($user, $company)
        ->get(route('api.tokens.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists tokens for a permitted member', function (): void {
    $company = workspace();
    $user = memberWith(['api.tokens.view'], $company);

    app(ApiTokenService::class)->create($user, $company->id, 'CI deploy', ['read']);

    actingAsMember($user, $company)
        ->get(route('api.tokens.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'api/tokens/index')
        ->assertJsonPath('props.table.rows.0.name', 'CI deploy');
});

it('shows the plaintext token exactly once and never persists it', function (): void {
    $company = workspace();
    $user = memberWith(['api.tokens.view', 'api.tokens.create'], $company);

    $response = actingAsMember($user, $company)->post(route('api.tokens.store'), [
        'name' => 'Reporting',
        'abilities' => ['read'],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('created_token');

    /** @var array{name: string, plain_text: string} $flashed */
    $flashed = session('created_token');

    expect($flashed['plain_text'])->toContain('|');

    // The plaintext is not recoverable: only its hash reaches the database.
    $stored = ApiToken::query()->where('company_id', $company->id)->firstOrFail();
    expect($stored->token)->not->toBe($flashed['plain_text'])
        ->and($stored->token)->toBe(hash('sha256', explode('|', $flashed['plain_text'])[1]));
});

it('surfaces the plaintext on the next render and never again', function (): void {
    $company = workspace();
    $user = memberWith(['api.tokens.view', 'api.tokens.create'], $company);

    actingAsMember($user, $company)
        ->post(route('api.tokens.store'), ['name' => 'Reporting', 'abilities' => ['read']])
        ->assertRedirect();

    // The shared `flash` bag only carries the four toast levels, so the index
    // screen reads the plaintext off its own prop — for one render only.
    $first = actingAsMember($user, $company)->get(route('api.tokens.index'), inertiaHeaders())->assertOk();

    expect($first->json('props.created_token.plain_text'))->toBeString();

    $second = actingAsMember($user, $company)->get(route('api.tokens.index'), inertiaHeaders())->assertOk();

    expect($second->json('props.created_token'))->toBeNull();
});

it('fails validation without abilities', function (): void {
    $company = workspace();
    $user = memberWith(['api.tokens.view', 'api.tokens.create'], $company);

    actingAsMember($user, $company)
        ->post(route('api.tokens.store'), ['name' => 'No abilities', 'abilities' => []])
        ->assertSessionHasErrors('abilities');
});

it('forbids creating a token without the create permission', function (): void {
    $company = workspace();
    $user = memberWith(['api.tokens.view'], $company);

    actingAsMember($user, $company)
        ->post(route('api.tokens.store'), ['name' => 'Nope', 'abilities' => ['read']])
        ->assertForbidden();
});

it('revokes a token', function (): void {
    $company = workspace();
    $user = memberWith(['api.tokens.view', 'api.tokens.revoke'], $company);

    $token = app(ApiTokenService::class)->create($user, $company->id, 'Temp', ['read'])['token'];

    actingAsMember($user, $company)
        ->delete(route('api.tokens.destroy', $token->id))
        ->assertRedirect();

    expect(ApiToken::query()->whereKey($token->id)->exists())->toBeFalse();
});

it('cannot revoke a token belonging to another workspace', function (): void {
    $other = workspace();
    $otherOwner = User::query()->findOrFail($other->owner_id);
    $foreignToken = app(ApiTokenService::class)->create($otherOwner, $other->id, 'Foreign', ['read'])['token'];

    $company = workspace();
    $user = memberWith(['api.tokens.view', 'api.tokens.revoke'], $company);

    actingAsMember($user, $company)
        ->delete(route('api.tokens.destroy', $foreignToken->id))
        ->assertForbidden();

    expect(ApiToken::query()->whereKey($foreignToken->id)->exists())->toBeTrue();
});

it('does not list another workspace tokens', function (): void {
    $other = workspace();
    $otherOwner = User::query()->findOrFail($other->owner_id);
    app(ApiTokenService::class)->create($otherOwner, $other->id, 'Foreign', ['read']);

    $company = workspace();
    $user = memberWith(['api.tokens.view'], $company);

    $response = actingAsMember($user, $company)
        ->get(route('api.tokens.index'), inertiaHeaders())
        ->assertOk();

    expect($response->json('props.table.rows'))->toBe([]);
});
