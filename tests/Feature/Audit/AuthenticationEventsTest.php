<?php

declare(strict_types=1);

use App\Modules\Audit\Models\SecurityLog;
use App\Modules\User\Models\LoginHistory;
use App\Modules\User\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

it('records a successful login with the parsed user agent', function (): void {
    $user = User::factory()->create();

    request()->headers->set('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Version/17.0 Mobile Safari/604.1');

    event(new Login('web', $user, false));

    $history = LoginHistory::query()->firstOrFail();

    expect($history->successful)->toBeTrue()
        ->and($history->user_id)->toBe($user->id)
        ->and($history->device_type)->toBe('mobile')
        ->and($history->platform)->toBe('iOS')
        ->and($history->browser)->toBe('Safari')
        ->and($user->refresh()->last_login_at)->not->toBeNull();
});

it('records a failed attempt against a known account', function (): void {
    $user = User::factory()->create();

    event(new Failed('web', $user, ['email' => $user->email, 'password' => 'wrong']));

    $history = LoginHistory::query()->firstOrFail();

    expect($history->successful)->toBeFalse()
        ->and($history->failure_reason)->toBe('invalid_credentials')
        ->and($history->user_id)->toBe($user->id);
});

it('records a failed attempt against an account that does not exist', function (): void {
    event(new Failed('web', null, ['email' => 'nobody@acme.test', 'password' => 'wrong']));

    $history = LoginHistory::query()->firstOrFail();

    expect($history->successful)->toBeFalse()
        ->and($history->user_id)->toBeNull()
        ->and($history->email)->toBe('nobody@acme.test')
        ->and($history->failure_reason)->toBe('unknown_account');
});

it('records a lockout as a throttled attempt', function (): void {
    event(new Lockout(Request::create('/login', 'POST', ['email' => 'target@acme.test'])));

    expect(LoginHistory::query()->firstOrFail()->failure_reason)->toBe('throttled');
});

it('leaves the security log alone for ordinary auth events', function (): void {
    $user = User::factory()->create();

    event(new Login('web', $user, false));
    event(new Failed('web', null, ['email' => 'nobody@acme.test']));

    expect(SecurityLog::query()->count())->toBe(0);
});

/**
 * Give the current request a real session, so the recorder has a session id to
 * key the login row on — without one there is nothing for logout to close.
 */
function withStartedSession(): void
{
    $session = app('session.store');
    $session->start();

    request()->setLaravelSession($session);
}

it('stamps the logout time on the row this session opened', function (): void {
    $user = User::factory()->create();
    withStartedSession();

    event(new Login('web', $user, false));

    $history = LoginHistory::query()->firstOrFail();
    expect($history->logged_out_at)->toBeNull();

    event(new Logout('web', $user));

    expect($history->refresh()->logged_out_at)->not->toBeNull();
});

it('closes only the newest open row when a session id repeats', function (): void {
    $user = User::factory()->create();
    withStartedSession();

    $sessionId = request()->session()->getId();

    // A login/logout/login cycle in the same browser can leave two rows behind
    // carrying the same session id; only the live one may be closed.
    $stale = LoginHistory::create([
        'user_id' => $user->id,
        'email' => $user->email,
        'successful' => true,
        'session_id' => $sessionId,
        'logged_in_at' => now()->subHour(),
    ]);

    event(new Login('web', $user, false));
    event(new Logout('web', $user));

    $current = LoginHistory::query()->whereKeyNot($stale->id)->firstOrFail();

    expect($stale->refresh()->logged_out_at)->toBeNull()
        ->and($current->logged_out_at)->not->toBeNull();
});

it('does not close another session\'s row on logout', function (): void {
    $user = User::factory()->create();
    withStartedSession();

    $other = LoginHistory::create([
        'user_id' => $user->id,
        'email' => $user->email,
        'successful' => true,
        'session_id' => 'a-different-session',
        'logged_in_at' => now(),
    ]);

    event(new Login('web', $user, false));
    event(new Logout('web', $user));

    expect($other->refresh()->logged_out_at)->toBeNull();
});
