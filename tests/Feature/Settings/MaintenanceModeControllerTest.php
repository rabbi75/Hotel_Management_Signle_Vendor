<?php

declare(strict_types=1);

use App\Modules\Settings\Http\Middleware\CheckMaintenanceMode;
use App\Support\Settings\SettingsRepository;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('admin.settings.maintenance.index'))->assertRedirect(route('admin.login'));
});

it('forbids a member without the toggle permission', function (): void {
    $member = platformAdminWith(['platform.settings.view']);

    actingAsAdmin($member)
        ->post(route('admin.settings.maintenance.enable'))
        ->assertForbidden();

    expect(setting('maintenance.enabled', false))->toBeFalse();
});

it('rejects an allow-list entry that is not an IP address', function (): void {
    $admin = platformAdminWith(['platform.settings.maintenance']);

    actingAsAdmin($admin)
        ->post(route('admin.settings.maintenance.enable'), ['allowed_ips' => ['not-an-ip']])
        ->assertSessionHasErrors('allowed_ips.0');
});

it('enables maintenance mode and surfaces the generated token exactly once', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.maintenance']);

    actingAsAdmin($admin)
        ->post(route('admin.settings.maintenance.enable'), [
            'message' => 'Upgrading the database.',
            'allowed_ips' => ['198.51.100.4'],
        ])
        ->assertRedirect(route('admin.settings.maintenance.index'))
        ->assertSessionHas('maintenance_secret');

    expect(setting('maintenance.enabled'))->toBeTrue()
        ->and(setting('maintenance.allowed_ips'))->toBe(['198.51.100.4']);

    $secret = (string) setting('maintenance.secret');

    // The flash carries it into the very next render...
    actingAsAdmin($admin)
        ->withSession(['maintenance_secret' => $secret])
        ->get(route('admin.settings.maintenance.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.generatedSecret', $secret);

    // ...and is gone on every visit after that.
    actingAsAdmin($admin)
        ->get(route('admin.settings.maintenance.index'), inertiaHeaders())
        ->assertJsonPath('props.generatedSecret', null);
});

it('disables maintenance mode', function (): void {
    $admin = platformAdminWith(['platform.settings.maintenance']);

    actingAsAdmin($admin)->post(route('admin.settings.maintenance.enable'));

    actingAsAdmin($admin)
        ->delete(route('admin.settings.maintenance.disable'))
        ->assertRedirect();

    expect(setting('maintenance.enabled'))->toBeFalse();
});

it('locks a guest out while maintenance mode is on', function (): void {
    app(SettingsRepository::class)->setMany(
        ['maintenance.enabled' => true, 'maintenance.message' => 'Back shortly.'],
        SettingsRepository::SCOPE_SYSTEM,
    );

    get(route('home'))->assertStatus(503);
});

it('lets the bypass token through and remembers it in a cookie', function (): void {
    $settings = app(SettingsRepository::class);
    $settings->set('maintenance.enabled', true);
    $settings->set('maintenance.secret', 'let-me-in-token', SettingsRepository::SCOPE_SYSTEM, null, true);

    // Redirected back to the same URL *without* the query string, so the token
    // never lingers in history or a referrer header.
    get(route('home').'?'.CheckMaintenanceMode::BYPASS_PARAMETER.'=let-me-in-token')
        ->assertRedirect(route('home'))
        ->assertCookie(CheckMaintenanceMode::BYPASS_COOKIE, 'let-me-in-token');
});

it('refuses a bypass token that does not match', function (): void {
    $settings = app(SettingsRepository::class);
    $settings->set('maintenance.enabled', true);
    $settings->set('maintenance.secret', 'let-me-in-token', SettingsRepository::SCOPE_SYSTEM, null, true);

    get(route('home').'?'.CheckMaintenanceMode::BYPASS_PARAMETER.'=guessed')->assertStatus(503);
});

it('lets an allow-listed address through', function (): void {
    app(SettingsRepository::class)->setMany(
        ['maintenance.enabled' => true, 'maintenance.allowed_ips' => ['198.51.100.4']],
        SettingsRepository::SCOPE_SYSTEM,
    );

    // Asserted as "not locked out" rather than 200: what the welcome page does
    // next is not this middleware's concern.
    expect(get(route('home'), ['REMOTE_ADDR' => '198.51.100.4'])->status())->not->toBe(503);
});
