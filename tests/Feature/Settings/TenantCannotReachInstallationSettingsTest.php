<?php

declare(strict_types=1);

use App\Support\Settings\SettingsRepository;

/*
|------------------------------------------------------------------------------
| Installation settings are the operator's
|------------------------------------------------------------------------------
|
| These panels used to sit on the tenant side behind `settings.*`, and the
| workspace `admin` role — the one CreateCompany assigns to whoever signs up —
| held `settings.*`. Every customer therefore had the SMTP credentials, the
| object-storage keys, the Stripe and OpenAI secrets, the password policy and
| the maintenance switch for the whole installation.
|
| This file is the regression net for that. If a future change puts any of
| these back on the `web` guard, these tests fail.
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

it('keeps the installation settings routes off the tenant guard', function (): void {
    $company = workspace();
    $owner = superAdmin($company);

    // A tenant *super*-admin, the most privileged identity on the web guard, is
    // still the wrong guard entirely and is bounced to the console's login.
    foreach ([
        'admin.settings.index',
        'admin.settings.mail.index',
        'admin.settings.storage.index',
        'admin.settings.api_keys.index',
        'admin.settings.security.index',
        'admin.settings.maintenance.index',
    ] as $name) {
        actingAsMember($owner, $company)
            ->get(route($name))
            ->assertRedirect(route('admin.login'));
    }
});

it('refuses a tenant write to installation settings', function (): void {
    $company = workspace();
    $owner = superAdmin($company);

    actingAsMember($owner, $company)
        ->put(route('admin.settings.mail.update'), [
            'mailer' => 'smtp',
            'host' => 'smtp.attacker.test',
            'from_address' => 'hello@attacker.test',
            'from_name' => 'Attacker',
        ])
        ->assertRedirect(route('admin.login'));

    expect(app(SettingsRepository::class)->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'mail.host'))
        ->not->toBe('smtp.attacker.test');
});

it('does not advertise installation settings in tenant navigation', function (): void {
    $company = workspace();
    $owner = superAdmin($company);

    $response = actingAsMember($owner, $company)
        ->get(route('dashboard'), inertiaHeaders())
        ->assertOk();

    $navigation = json_encode($response->json('props.navigation'));

    expect($navigation)->not->toContain('settings.mail')
        ->and($navigation)->not->toContain('settings.api_keys')
        ->and($navigation)->not->toContain('settings.maintenance');
});
