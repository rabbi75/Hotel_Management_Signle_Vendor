<?php

declare(strict_types=1);

use App\Modules\User\Models\LoginHistory;
use App\Modules\User\Models\User;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('settings.login-history'))->assertRedirect(route('login'));
});

it('shows only the signed-in user own history', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();
    $other = memberWith([], $company);

    LoginHistory::create([
        'user_id' => $user->id,
        'email' => $user->email,
        'ip_address' => '10.0.0.1',
        'browser' => 'Chrome',
        'platform' => 'macOS',
        'device_type' => 'desktop',
        'successful' => true,
        'logged_in_at' => now(),
    ]);

    LoginHistory::create([
        'user_id' => $other->id,
        'email' => $other->email,
        'ip_address' => '10.0.0.2',
        'browser' => 'Firefox',
        'platform' => 'Windows',
        'device_type' => 'desktop',
        'successful' => true,
        'logged_in_at' => now(),
    ]);

    $response = actingAsMember($user, $company)
        ->get(route('settings.login-history'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'settings/login-history');

    $addresses = collect($response->json('props.table.rows'))->pluck('ip_address')->all();

    expect($addresses)->toBe(['10.0.0.1']);
});

it('filters failed attempts', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    foreach ([true, false] as $successful) {
        LoginHistory::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'successful' => $successful,
            'failure_reason' => $successful ? null : 'invalid_credentials',
            'logged_in_at' => now(),
        ]);
    }

    $response = actingAsMember($user, $company)
        ->get(route('settings.login-history', ['logins_filters' => ['successful' => '0']]), inertiaHeaders())
        ->assertOk();

    $rows = $response->json('props.table.rows');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['successful'])->toBeFalse();
});

it('is empty for a user who has never signed in', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();

    actingAsMember($user, $company)
        ->get(route('settings.login-history'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.table.meta.total', 0);
});
