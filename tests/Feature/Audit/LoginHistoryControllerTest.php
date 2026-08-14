<?php

declare(strict_types=1);

use App\Modules\User\Models\LoginHistory;
use App\Modules\User\Models\User;

use function Pest\Laravel\get;

/**
 * @param  array<string, mixed>  $attributes
 */
function loginRow(?User $user, bool $successful = true, array $attributes = []): LoginHistory
{
    $row = new LoginHistory([
        'user_id' => $user?->id,
        'email' => $user?->email ?? 'ghost@acme.test',
        'ip_address' => '198.51.100.4',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0 Safari/537.36',
        'device_type' => 'desktop',
        'platform' => 'Windows',
        'browser' => 'Chrome',
        'successful' => $successful,
        'failure_reason' => $successful ? null : 'invalid_credentials',
        'logged_in_at' => now(),
    ]);

    $row->fill($attributes)->save();

    return $row;
}

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('audit.logins.index'))->assertRedirect(route('login'));
});

it('forbids a member without the login view permission', function (): void {
    $company = workspace();
    $member = memberWith(['audit.activity.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('audit.logins.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists login attempts with their parsed device details', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.login.view'], $company)->refresh();

    loginRow($viewer);

    actingAsMember($viewer, $company)
        ->get(route('audit.logins.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'audit/logins')
        ->assertJsonPath('props.table.rows.0.browser', 'Chrome')
        ->assertJsonPath('props.table.rows.0.platform', 'Windows')
        ->assertJsonPath('props.table.rows.0.successful', true);
});

it('filters login history down to failures', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.login.view'], $company)->refresh();

    loginRow($viewer, true);
    loginRow(null, false);

    actingAsMember($viewer, $company)
        ->get(route('audit.logins.index', ['logins_filters' => ['successful' => '0']]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.table.rows')
        ->assertJsonPath('props.table.rows.0.failure_reason', 'invalid_credentials');
});

it('filters login history by date range', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.login.view'], $company)->refresh();

    loginRow($viewer, true, ['logged_in_at' => now()->subMonth()]);
    loginRow($viewer, true);

    actingAsMember($viewer, $company)
        ->get(route('audit.logins.index', [
            'logins_filters' => ['attempted' => ['from' => now()->subDay()->toDateString()]],
        ]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.table.rows');
});

it('forbids an export without the export permission', function (): void {
    $company = workspace();
    $viewer = memberWith(['audit.login.view'], $company)->refresh();

    actingAsMember($viewer, $company)
        ->get(route('audit.logins.export'))
        ->assertForbidden();
});

it('exports login history matching the on-screen filters', function (): void {
    $company = workspace();
    $auditor = memberWith(['audit.login.view', 'audit.export'], $company)->refresh();

    loginRow($auditor, true);
    loginRow(null, false);

    actingAsMember($auditor, $company)
        ->get(route('audit.logins.export', ['format' => 'csv', 'logins_filters' => ['successful' => '0']]))
        ->assertOk()
        ->assertDownload();
});
