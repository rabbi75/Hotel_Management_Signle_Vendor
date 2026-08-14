<?php

declare(strict_types=1);

use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Services\ApiTokenService;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function bearer(User $user, Company $company, array $abilities = ['read', 'write', 'delete']): array
{
    $result = app(ApiTokenService::class)->create($user, $company->id, 'Test token', $abilities);

    return ['Authorization' => 'Bearer '.$result['plain_text'], 'Accept' => 'application/json'];
}

it('rejects an unauthenticated request with a problem document', function (): void {
    workspace();

    $response = $this->getJson('/api/v1/users');

    $response->assertStatus(401);
    expect($response->json('status'))->toBe(401)
        ->and($response->json('title'))->not->toBeNull();
});

it('returns a collection envelope with meta and links', function (): void {
    $company = workspace();
    $user = memberWith(['users.view'], $company)->refresh();

    $response = $this->withHeaders(bearer($user, $company))->getJson('/api/v1/users');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['per_page', 'next_cursor', 'prev_cursor'], 'links' => ['self', 'next', 'prev']]);
});

it('cannot read another workspace data', function (): void {
    $other = workspace();
    $stranger = User::factory()->create(['name' => 'Foreign Person']);
    $other->members()->attach($stranger->id, ['role' => CompanyRole::Member->value, 'joined_at' => now()]);

    $company = workspace();
    $user = memberWith(['users.view'], $company)->refresh();

    $response = $this->withHeaders(bearer($user, $company))->getJson('/api/v1/users');

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->not->toContain('Foreign Person');
});

it('rejects a revoked token', function (): void {
    $company = workspace();
    $user = memberWith(['users.view'], $company)->refresh();

    $headers = bearer($user, $company);

    ApiToken::query()->where('company_id', $company->id)->delete();

    $this->withHeaders($headers)->getJson('/api/v1/users')->assertStatus(401);
});

it('rejects an expired token', function (): void {
    $company = workspace();
    $user = memberWith(['users.view'], $company)->refresh();

    $headers = bearer($user, $company);

    ApiToken::query()
        ->where('company_id', $company->id)
        ->update(['expires_at' => CarbonImmutable::now()->subDay()]);

    $this->withHeaders($headers)->getJson('/api/v1/users')->assertStatus(401);
});

it('rejects a token whose abilities do not cover the action', function (): void {
    $company = workspace();
    $user = memberWith(['users.view', 'users.create'], $company)->refresh();

    $this->withHeaders(bearer($user, $company, ['read']))
        ->postJson('/api/v1/users', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'correct-horse-battery',
        ])
        ->assertStatus(403);
});

it('returns a problem document on validation failure', function (): void {
    $company = workspace();
    $user = memberWith(['users.view', 'users.create'], $company)->refresh();

    $response = $this->withHeaders(bearer($user, $company))->postJson('/api/v1/users', ['first_name' => '']);

    $response->assertStatus(422);
    expect($response->json('errors'))->toHaveKey('email')
        ->and($response->json('status'))->toBe(422);
});

it('creates a user in the token workspace', function (): void {
    $company = workspace();
    $user = memberWith(['users.view', 'users.create'], $company)->refresh();

    $response = $this->withHeaders(bearer($user, $company))->postJson('/api/v1/users', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery',
    ]);

    $response->assertStatus(201)->assertJsonPath('data.email', 'ada@example.com');

    $created = User::query()->where('email', 'ada@example.com')->firstOrFail();
    expect($created->companies()->whereKey($company->id)->exists())->toBeTrue();
});

it('serves an OpenAPI 3.1 document without a token', function (): void {
    workspace();

    $response = $this->getJson('/api/v1/openapi.json');

    $response->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonStructure(['info', 'servers', 'components' => ['schemas' => ['Problem', 'Collection']], 'paths']);

    expect($response->json('paths'))->toHaveKey('/users');
});
