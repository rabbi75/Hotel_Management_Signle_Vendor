<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('admin.settings.appearance.index'))->assertRedirect(route('admin.login'));
});

it('forbids a member without the general update permission', function (): void {
    $member = platformAdminWith(['platform.settings.view']);

    actingAsAdmin($member)
        ->put(route('admin.settings.appearance.update'), [
            'theme' => 'dark',
            'primary_color' => '#000000',
            'sidebar_variant' => 'inset',
        ])
        ->assertForbidden();
});

it('renders the appearance panel with the theme options', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.general']);

    actingAsAdmin($admin)
        ->get(route('admin.settings.appearance.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'admin/settings/appearance')
        ->assertJsonPath('props.themes.0.value', 'light');
});

it('updates the appearance panel', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.appearance.update'), [
            'theme' => 'dark',
            'primary_color' => '#ff8800',
            'sidebar_variant' => 'floating',
        ])
        ->assertRedirect();

    expect(setting('appearance.theme'))->toBe('dark')
        ->and(setting('appearance.primary_color'))->toBe('#ff8800');
});

it('rejects a colour that is not a hex triplet', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.appearance.update'), [
            'theme' => 'dark',
            'primary_color' => 'orange',
            'sidebar_variant' => 'floating',
        ])
        ->assertSessionHasErrors('primary_color');
});
