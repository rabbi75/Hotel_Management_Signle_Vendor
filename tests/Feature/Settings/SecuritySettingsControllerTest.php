<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('admin.settings.security.index'))->assertRedirect(route('admin.login'));
});

it('challenges for the password before showing the panel', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.security']);

    actingAsAdmin($admin)
        ->get(route('admin.settings.security.index'))
        ->assertRedirect(route('admin.password.confirm'));
});

it('forbids a member without the security update permission', function (): void {
    $member = platformAdminWith(['platform.settings.view']);
    confirmSettingsPassword();

    actingAsAdmin($member)
        ->put(route('admin.settings.security.update'), [
            'password_min_length' => 8,
            'max_login_attempts' => 3,
            'session_lifetime_minutes' => 60,
        ])
        ->assertForbidden();
});

it('renders the security panel', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.security']);
    confirmSettingsPassword();

    actingAsAdmin($admin)
        ->get(route('admin.settings.security.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'admin/settings/security')
        ->assertJsonStructure(['props' => ['settings' => ['password_min_length', 'max_login_attempts', 'allowed_ips']]]);
});

it('updates the security panel', function (): void {
    $admin = platformAdminWith(['platform.settings.security']);
    confirmSettingsPassword();

    actingAsAdmin($admin)
        ->put(route('admin.settings.security.update'), [
            'password_min_length' => 16,
            'password_requires_symbols' => true,
            'max_login_attempts' => 3,
            'session_lifetime_minutes' => 45,
            'two_factor_enforced' => true,
            'allowed_ips' => ['198.51.100.4'],
        ])
        ->assertRedirect();

    expect(setting('security.password_min_length'))->toBe(16)
        ->and(setting('security.two_factor_enforced'))->toBeTrue()
        ->and(setting('security.allowed_ips'))->toBe(['198.51.100.4']);
});

it('rejects a password length outside the supported range', function (): void {
    $admin = platformAdminWith(['platform.settings.security']);
    confirmSettingsPassword();

    actingAsAdmin($admin)
        ->put(route('admin.settings.security.update'), [
            'password_min_length' => 4,
            'max_login_attempts' => 3,
            'session_lifetime_minutes' => 45,
        ])
        ->assertSessionHasErrors('password_min_length');
});
