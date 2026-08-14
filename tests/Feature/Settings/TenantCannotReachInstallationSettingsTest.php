<?php

declare(strict_types=1);

use App\Support\Settings\SettingsRepository;

/*
|------------------------------------------------------------------------------
| Installation settings
|------------------------------------------------------------------------------
|
| SMTP, storage keys, API secrets and maintenance mode configure the whole
| hotel installation. Ordinary staff must not reach them; the hotel
| super-admin (and anyone granted platform.settings.*) may.
|
*/

it('does not grant a workspace owner any installation settings permission', function (): void {
    $company = workspace();
    $owner = $company->owner;

    expect($owner->can('settings.mail.update'))->toBeFalse()
        ->and($owner->can('settings.api_keys.manage'))->toBeFalse()
        ->and($owner->can('settings.maintenance.toggle'))->toBeFalse()
        ->and($owner->can('platform.settings.mail'))->toBeFalse()
        ->and($owner->can('platform.settings.api_keys'))->toBeFalse();
});

it('forbids a staff member from installation settings', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view'], $company)->refresh();

    foreach ([
        'admin.settings.index',
        'admin.settings.mail.index',
        'admin.settings.storage.index',
        'admin.settings.maintenance.index',
    ] as $name) {
        actingAsMember($member, $company)
            ->get(route($name))
            ->assertForbidden();
    }
});

it('refuses a staff write to installation settings', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('admin.settings.mail.update'), [
            'mailer' => 'smtp',
            'host' => 'smtp.attacker.test',
            'from_address' => 'hello@attacker.test',
            'from_name' => 'Attacker',
        ])
        ->assertForbidden();

    expect(app(SettingsRepository::class)->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'mail.host'))
        ->not->toBe('smtp.attacker.test');
});

it('allows the hotel super-admin to open installation settings', function (): void {
    $company = workspace();
    $owner = superAdmin($company);

    actingAsMember($owner, $company)
        ->get(route('admin.settings.index'), inertiaHeaders())
        ->assertOk();
});
