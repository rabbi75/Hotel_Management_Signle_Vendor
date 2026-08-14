<?php

declare(strict_types=1);

use App\Modules\Settings\Models\Setting;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('admin.settings.index'))->assertRedirect(route('login'));
});

it('forbids a member without the view permission', function (): void {
    $member = platformAdminWith([]);

    actingAsAdmin($member)
        ->get(route('admin.settings.index'), inertiaHeaders())
        ->assertForbidden();
});

it('renders the general panel with its schema-derived values', function (): void {
    $viewer = platformAdminWith(['platform.settings.view']);

    actingAsAdmin($viewer)
        ->get(route('admin.settings.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'admin/settings/general')
        ->assertJsonPath('props.group', 'general')
        ->assertJsonStructure(['props' => ['settings' => ['app_name', 'support_email'], 'secrets', 'tabs']]);
});

it('forbids updating without the general update permission', function (): void {
    $viewer = platformAdminWith(['platform.settings.view']);

    actingAsAdmin($viewer)
        ->put(route('admin.settings.general.update'), [
            'app_name' => 'Hijacked',
            'short_name' => 'Hij',
            'support_email' => 'nope@example.com',
        ])
        ->assertForbidden();

    expect(setting('general.app_name'))->not->toBe('Hijacked');
});

it('updates the general panel', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.general.update'), [
            'app_name' => 'Acme Suite',
            'short_name' => 'Acme',
            'support_email' => 'help@acme.test',
            'registration_enabled' => false,
        ])
        ->assertRedirect();

    expect(setting('general.app_name'))->toBe('Acme Suite')
        ->and(setting('general.registration_enabled'))->toBeFalse();
});

it('rejects an invalid support email', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.general.update'), [
            'app_name' => 'Acme Suite',
            'short_name' => 'Acme',
            'support_email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('support_email');
});

it('never writes a key the schema does not declare', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.general.update'), [
            'app_name' => 'Acme Suite',
            'short_name' => 'Acme',
            'support_email' => 'help@acme.test',
            'is_admin' => true,
        ])
        ->assertRedirect();

    expect(Setting::query()->where('key', 'general.is_admin')->exists())->toBeFalse();
});
