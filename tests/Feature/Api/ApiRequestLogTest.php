<?php

declare(strict_types=1);

use App\Modules\Api\Models\ApiRequestLog;
use App\Modules\Api\Services\ApiTokenService;
use App\Modules\Api\Support\LogRedactor;

it('records one row per public API request', function (): void {
    config(['saas.api.log_requests' => true, 'saas.api.log_bodies' => false]);

    $company = workspace();
    $user = memberWith(['users.view'], $company)->refresh();
    $plain = app(ApiTokenService::class)->create($user, $company->id, 'Logged', ['read'])['plain_text'];

    $this->withHeaders(['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'])
        ->getJson('/api/v1/users')
        ->assertOk();

    $log = ApiRequestLog::query()->where('company_id', $company->id)->firstOrFail();

    expect($log->method)->toBe('GET')
        ->and($log->path)->toBe('api/v1/users')
        ->and($log->status)->toBe(200)
        ->and($log->api_token_id)->not->toBeNull()
        ->and($log->request_body)->toBeNull();
});

it('honours the log_requests switch', function (): void {
    config(['saas.api.log_requests' => false]);

    $company = workspace();
    $user = memberWith(['users.view'], $company)->refresh();
    $plain = app(ApiTokenService::class)->create($user, $company->id, 'Silent', ['read'])['plain_text'];

    $this->withHeaders(['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'])
        ->getJson('/api/v1/users')
        ->assertOk();

    expect(ApiRequestLog::query()->count())->toBe(0);
});

it('redacts credentials at any nesting depth', function (): void {
    $redacted = LogRedactor::redact([
        'headers' => ['authorization' => 'Bearer secret-value', 'x-request-id' => 'abc'],
        'body' => [
            'name' => 'Ada',
            'password' => 'hunter2',
            'nested' => [
                'level2' => [
                    'level3' => ['api_key' => 'sk-live-123', 'refresh_token' => 'rt-999', 'keep' => 'visible'],
                ],
            ],
            'clients' => [
                ['client_secret' => 'shh', 'label' => 'primary'],
            ],
        ],
    ]);

    expect($redacted['headers']['authorization'])->toBe(LogRedactor::REDACTED)
        ->and($redacted['headers']['x-request-id'])->toBe('abc')
        ->and($redacted['body']['password'])->toBe(LogRedactor::REDACTED)
        ->and($redacted['body']['nested']['level2']['level3']['api_key'])->toBe(LogRedactor::REDACTED)
        ->and($redacted['body']['nested']['level2']['level3']['refresh_token'])->toBe(LogRedactor::REDACTED)
        ->and($redacted['body']['nested']['level2']['level3']['keep'])->toBe('visible')
        ->and($redacted['body']['clients'][0]['client_secret'])->toBe(LogRedactor::REDACTED)
        ->and($redacted['body']['clients'][0]['label'])->toBe('primary');
});

it('never persists an authorization header when bodies are logged', function (): void {
    config(['saas.api.log_requests' => true, 'saas.api.log_bodies' => true]);

    $company = workspace();
    $user = memberWith(['users.view', 'users.create'], $company)->refresh();
    $plain = app(ApiTokenService::class)->create($user, $company->id, 'Bodies', ['read', 'write'])['plain_text'];

    $this->withHeaders(['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'])
        ->postJson('/api/v1/users', ['first_name' => '', 'password' => 'hunter2'])
        ->assertStatus(422);

    $log = ApiRequestLog::query()->where('company_id', $company->id)->firstOrFail();
    $encoded = json_encode($log->request_body);

    expect($encoded)->not->toContain($plain)
        ->and($encoded)->not->toContain('hunter2')
        ->and($encoded)->toContain(LogRedactor::REDACTED);
});

it('forbids a member without the log permission', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)
        ->get(route('api.logs.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only logs from the active workspace', function (): void {
    $other = workspace();
    ApiRequestLog::factory()->create(['company_id' => $other->id, 'path' => 'api/v1/secret']);

    $company = workspace();
    ApiRequestLog::factory()->create(['company_id' => $company->id, 'path' => 'api/v1/mine']);

    $user = memberWith(['api.logs.view'], $company)->refresh();

    $response = actingAsMember($user, $company)
        ->get(route('api.logs.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'api/logs/index');

    $paths = collect($response->json('props.table.rows'))->pluck('path')->all();

    expect($paths)->toContain('api/v1/mine')->and($paths)->not->toContain('api/v1/secret');
});

it('prunes logs older than the retention window', function (): void {
    $company = workspace();

    ApiRequestLog::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(90)]);
    ApiRequestLog::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDay()]);

    $this->artisan('api:prune-logs', ['--days' => 30])->assertSuccessful();

    expect(ApiRequestLog::query()->count())->toBe(1);
});
