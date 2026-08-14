<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Enums\Severity;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Platform\Http\Middleware\ConfirmAdminPassword;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('audit.security.index'))->assertRedirect(route('login'));
});

it('forbids a member without the security view permission', function (): void {
    $company = workspace();
    $member = memberWith(['audit.activity.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('audit.security.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists security events with their severity', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.security.view'], $company)->refresh();

    app(SecurityLogger::class)->log(SecurityEvent::PasswordChanged, $viewer);

    actingAsMember($viewer, $company)
        ->get(route('audit.security.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'audit/security')
        ->assertJsonPath('props.table.rows.0.event', SecurityEvent::PasswordChanged->value)
        ->assertJsonPath('props.table.rows.0.severity', Severity::Notice->value)
        ->assertJsonStructure(['props' => ['events', 'severities']]);
});

it('filters the security log by event', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.security.view'], $company)->refresh();

    $logger = app(SecurityLogger::class);
    $logger->log(SecurityEvent::PasswordChanged, $viewer);
    $logger->log(SecurityEvent::TwoFactorEnabled, $viewer);

    actingAsMember($viewer, $company)
        ->get(route('audit.security.index', [
            'security_filters' => ['event' => [SecurityEvent::TwoFactorEnabled->value]],
        ]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.table.rows')
        ->assertJsonPath('props.table.rows.0.event', SecurityEvent::TwoFactorEnabled->value);
});

it('records a settings change without recording the values', function (): void {
    // Settings are the operator's, so the change is made from the console and
    // the entry is attributed to an admin rather than to a workspace member.
    $admin = platformAdminWith(['platform.settings.api_keys']);
    session([ConfirmAdminPassword::SESSION_KEY => time()]);

    actingAsAdmin($admin)
        ->put(route('admin.settings.api_keys.update'), ['openai_api_key' => 'sk-should-never-be-logged'])
        ->assertRedirect();

    $entry = SecurityLog::query()->where('event', SecurityEvent::SettingsChanged->value)->latest('id')->firstOrFail();

    expect($entry->admin_id)->toBe($admin->id)
        ->and($entry->user_id)->toBeNull()
        ->and($entry->context['keys'][0])->toBe('api_keys.openai_api_key')
        // The whole point: the key names are recorded, the credential is not.
        ->and(json_encode($entry->context))->not->toContain('sk-should-never-be-logged');
});

it('forbids an export without the export permission', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.security.view'], $company)->refresh();

    actingAsMember($viewer, $company)
        ->get(route('audit.security.export'))
        ->assertForbidden();
});

it('exports the security log', function (): void {
    $company = workspace();
    $auditor = memberWith(['audit.security.view', 'audit.export'], $company)->refresh();

    app(SecurityLogger::class)->log(SecurityEvent::RoleAssigned, $auditor);

    actingAsMember($auditor, $company)
        ->get(route('audit.security.export', ['format' => 'xlsx']))
        ->assertOk()
        ->assertDownload();
});
