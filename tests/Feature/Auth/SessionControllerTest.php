<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

/**
 * The session list reads the `sessions` table directly, so these tests run on
 * the database driver regardless of what the suite defaults to.
 */
beforeEach(function (): void {
    config(['session.driver' => 'database']);
});

it('redirects a guest to the login screen', function (): void {
    get(route('settings.sessions.index'))->assertRedirect(route('login'));
});

it('lists the user own sessions and flags nothing belonging to anyone else', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();
    $other = memberWith([], $company);

    seedSession('mine-1', $user, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120');
    seedSession('theirs-1', $other, 'Mozilla/5.0 (Windows NT 10.0) Firefox/120');

    $response = actingAsMember($user, $company)
        ->get(route('settings.sessions.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'settings/sessions')
        ->assertJsonPath('props.supported', true);

    $ids = collect($response->json('props.sessions'))->pluck('id')->all();

    expect($ids)->toContain('mine-1')
        ->and($ids)->not->toContain('theirs-1');

    $mine = collect($response->json('props.sessions'))->firstWhere('id', 'mine-1');

    expect($mine['platform'])->toBe('macOS')
        ->and($mine['browser'])->toBe('Chrome')
        ->and($mine['device'])->toBe('desktop');
});

it('requires a confirmed password to revoke sessions', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)
        ->delete(route('settings.sessions.destroy-others'), ['password' => 'password'])
        ->assertRedirect(route('password.confirm'));
});

it('rejects the wrong password when revoking other sessions', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('settings.sessions.destroy-others'), ['password' => 'not-my-password'])
        ->assertSessionHasErrors('password');
});

it('revokes every other session but leaves the current one alive', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    $test = actingAsMember($user, $company)->withSession(['auth.password_confirmed_at' => time()]);

    seedSession('other-a', $user);
    seedSession('other-b', $user);

    $response = $test->delete(route('settings.sessions.destroy-others'), ['password' => 'password']);
    $response->assertRedirect();

    $currentId = $response->baseResponse->getRequest()?->session()->getId();

    expect(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())
        ->not->toContain('other-a')
        ->not->toContain('other-b');

    // The request that did the revoking is still authenticated afterwards.
    $this->assertAuthenticatedAs($user->fresh());

    expect($currentId)->not->toBeNull()
        ->and($user->fresh()?->remember_token)->toBeNull();

    expect(SecurityLog::query()->where('user_id', $user->id)->pluck('event')->all())
        ->toContain(SecurityEvent::OtherSessionsRevoked);
});

it('revokes a single named session', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    seedSession('target', $user);

    actingAsMember($user, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('settings.sessions.destroy', 'target'))
        ->assertRedirect();

    expect(DB::table('sessions')->where('id', 'target')->exists())->toBeFalse();

    expect(SecurityLog::query()->where('user_id', $user->id)->pluck('event')->all())
        ->toContain(SecurityEvent::SessionRevoked);
});

it('will not revoke a session belonging to someone else', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();
    $other = memberWith([], $company);

    seedSession('not-yours', $other);

    actingAsMember($user, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('settings.sessions.destroy', 'not-yours'))
        ->assertNotFound();

    expect(DB::table('sessions')->where('id', 'not-yours')->exists())->toBeTrue();
});

function seedSession(string $id, User $user, string $agent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120'): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => $agent,
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);
}
