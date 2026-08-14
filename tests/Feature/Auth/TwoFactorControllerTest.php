<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\User\Models\User;

use function Pest\Laravel\get;

use PragmaRX\Google2FA\Google2FA;

it('redirects a guest to the login screen', function (): void {
    get(route('settings.two-factor.show'))->assertRedirect(route('login'));
});

it('shows the disabled state to a user who has not enrolled', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)
        ->get(route('settings.two-factor.show'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'settings/two-factor')
        ->assertJsonPath('props.enabled', false)
        ->assertJsonPath('props.pending', false)
        ->assertJsonPath('props.secret', null)
        ->assertJsonPath('props.qrCodeSvg', null);
});

it('requires a confirmed password before enrolling', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)
        ->post(route('settings.two-factor.store'))
        ->assertRedirect(route('password.confirm'));
});

it('walks a user through enable, confirm and disable', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    // -- enable ----------------------------------------------------------
    actingAsMember($user, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.two-factor.store'))
        ->assertRedirect();

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    // The enrolment screen hands out the secret and QR only while pending.
    actingAsMember($user, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.show'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.pending', true)
        ->assertJsonMissingPath('props.secret.0');

    // -- confirm ---------------------------------------------------------
    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));

    actingAsMember($user, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.two-factor.confirm'), ['code' => $code])
        ->assertRedirect();

    $user->refresh();

    expect($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeTrue();

    // -- recovery codes --------------------------------------------------
    $codes = actingAsMember($user, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson(route('settings.two-factor.recovery-codes'))
        ->assertOk()
        ->json('codes');

    expect($codes)->toHaveCount(8);

    // -- disable ---------------------------------------------------------
    actingAsMember($user, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('settings.two-factor.destroy'))
        ->assertRedirect();

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    expect(SecurityLog::query()->where('user_id', $user->id)->pluck('event')->all())
        ->toContain(SecurityEvent::TwoFactorEnabled)
        ->toContain(SecurityEvent::TwoFactorDisabled);
});

it('rejects an invalid confirmation code', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.two-factor.store'));

    actingAsMember($user->refresh(), $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors();

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
});

it('requires a code when confirming', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.two-factor.confirm'), [])
        ->assertSessionHasErrors('code');
});

it('regenerates recovery codes', function (): void {
    $company = workspace();
    $user = User::factory()->withTwoFactor()->create();
    $company->members()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    $before = $user->refresh()->recoveryCodes();

    actingAsMember($user->refresh(), $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.two-factor.recovery-codes.regenerate'))
        ->assertRedirect();

    expect($user->refresh()->recoveryCodes())->not->toBe($before);

    expect(SecurityLog::query()->where('user_id', $user->id)->pluck('event')->all())
        ->toContain(SecurityEvent::RecoveryCodesRegenerated);
});
