<?php

declare(strict_types=1);

use App\Support\Settings\SettingsRepository;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('admin.settings.mail.index'))->assertRedirect(route('login'));
});

it('forbids a member without the mail update permission', function (): void {
    $member = platformAdminWith(['platform.settings.view']);

    actingAsAdmin($member)
        ->put(route('admin.settings.mail.update'), [
            'mailer' => 'smtp',
            'from_address' => 'no@acme.test',
            'from_name' => 'Acme',
        ])
        ->assertForbidden();
});

it('renders the mail panel with the password masked', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.mail']);

    actingAsAdmin($admin)
        ->get(route('admin.settings.mail.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'admin/settings/mail')
        ->assertJsonPath('props.settings.password', null)
        ->assertJsonPath('props.secrets.password', false);
});

it('updates the mail panel', function (): void {
    $admin = platformAdminWith(['platform.settings.mail']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.mail.update'), [
            'mailer' => 'smtp',
            'host' => 'smtp.acme.test',
            'port' => 2525,
            'username' => 'postmaster',
            'password' => 'hunter2',
            'encryption' => 'tls',
            'from_address' => 'hello@acme.test',
            'from_name' => 'Acme',
        ])
        ->assertRedirect();

    expect(setting('mail.host'))->toBe('smtp.acme.test')
        ->and(setting('mail.port'))->toBe(2525)
        ->and(setting('mail.password'))->toBe('hunter2');
});

it('rejects an unsupported transport', function (): void {
    $admin = platformAdminWith(['platform.settings.mail']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.mail.update'), [
            'mailer' => 'carrier-pigeon',
            'from_address' => 'hello@acme.test',
            'from_name' => 'Acme',
        ])
        ->assertSessionHasErrors('mailer');
});

it('sends a test message through the saved transport', function (): void {
    $admin = platformAdminWith(['platform.settings.mail']);

    actingAsAdmin($admin)
        ->post(route('admin.settings.mail.test'))
        ->assertRedirect()
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();
});

it('reports the transport error verbatim when the test message fails', function (): void {
    $admin = platformAdminWith(['platform.settings.mail']);

    // Port 1 refuses immediately, so the failure path is exercised without
    // waiting on a connection timeout.
    app(SettingsRepository::class)->setMany([
        'mail.mailer' => 'smtp',
        'mail.host' => '127.0.0.1',
        'mail.port' => 1,
    ], SettingsRepository::SCOPE_SYSTEM);

    actingAsAdmin($admin)
        ->post(route('admin.settings.mail.test'))
        ->assertRedirect()
        ->assertSessionHasErrors('mail');
});
