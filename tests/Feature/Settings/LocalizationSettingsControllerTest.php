<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('admin.settings.localization.index'))->assertRedirect(route('login'));
});

it('forbids a member without the general update permission', function (): void {
    $member = platformAdminWith(['platform.settings.view']);

    actingAsAdmin($member)
        ->put(route('admin.settings.localization.update'), [
            'default_locale' => 'bn',
            'default_timezone' => 'UTC',
            'default_currency' => 'BDT',
            'date_format' => 'd M Y',
            'time_format' => 'H:i',
            'week_starts_on' => 0,
        ])
        ->assertForbidden();
});

it('renders the localization panel with the locale catalogue', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.general']);

    actingAsAdmin($admin)
        ->get(route('admin.settings.localization.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'admin/settings/localization')
        ->assertJsonPath('props.locales.en.name', 'English')
        ->assertJsonStructure(['props' => ['timezones']]);
});

it('updates the localization panel', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.localization.update'), [
            'default_locale' => 'bn',
            'default_timezone' => 'Asia/Dhaka',
            'default_currency' => 'BDT',
            'date_format' => 'd/m/Y',
            'time_format' => 'h:i A',
            'week_starts_on' => 0,
            'enabled_locales' => ['en', 'bn'],
        ])
        ->assertRedirect();

    expect(setting('localization.default_locale'))->toBe('bn')
        ->and(setting('localization.week_starts_on'))->toBe(0)
        ->and(setting('localization.enabled_locales'))->toBe(['en', 'bn']);
});

it('rejects a locale the application does not ship', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.localization.update'), [
            'default_locale' => 'kl',
            'default_timezone' => 'UTC',
            'default_currency' => 'USD',
            'date_format' => 'd M Y',
            'time_format' => 'H:i',
            'week_starts_on' => 1,
        ])
        ->assertSessionHasErrors('default_locale');
});

it('rejects an unknown timezone', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.localization.update'), [
            'default_locale' => 'en',
            'default_timezone' => 'Mars/Olympus',
            'default_currency' => 'USD',
            'date_format' => 'd M Y',
            'time_format' => 'H:i',
            'week_starts_on' => 1,
        ])
        ->assertSessionHasErrors('default_timezone');
});
