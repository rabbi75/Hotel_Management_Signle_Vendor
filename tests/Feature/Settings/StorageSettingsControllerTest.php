<?php

declare(strict_types=1);

use App\Modules\Settings\Support\SettingsSchema;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('admin.settings.storage.index'))->assertRedirect(route('admin.login'));
});

it('forbids a member without the storage update permission', function (): void {
    $member = platformAdminWith(['platform.settings.view']);

    actingAsAdmin($member)
        ->put(route('admin.settings.storage.update'), ['disk' => 's3', 'max_upload_kb' => 1024])
        ->assertForbidden();
});

it('renders the storage panel with the available drivers', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.storage']);

    actingAsAdmin($admin)
        ->get(route('admin.settings.storage.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'admin/settings/storage')
        ->assertJsonPath('props.drivers.3.value', 'r2');
});

it('stores Cloudflare R2 credentials encrypted', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.storage']);

    $response = actingAsAdmin($admin)
        ->put(route('admin.settings.storage.update'), [
            'disk' => 'r2',
            'max_upload_kb' => 20480,
            'r2_account_id' => 'acct-123',
            'r2_access_key_id' => 'r2-key-id',
            'r2_secret_access_key' => 'r2-super-secret',
            'r2_bucket' => 'uploads',
        ])
        ->assertRedirect();

    expect(setting('storage.disk'))->toBe('r2')
        ->and(setting('storage.r2_secret_access_key'))->toBe('r2-super-secret');

    $panel = actingAsAdmin($admin)
        ->get(route('admin.settings.storage.index'), inertiaHeaders())
        ->assertJsonPath('props.settings.r2_secret_access_key', SettingsSchema::MASK);

    expect($panel->getContent())->not->toContain('r2-super-secret')
        ->and($response->getContent())->not->toContain('r2-super-secret');
});

it('rejects an unknown disk', function (): void {
    $admin = platformAdminWith(['platform.settings.storage']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.storage.update'), ['disk' => 'dropbox', 'max_upload_kb' => 1024])
        ->assertSessionHasErrors('disk');
});
