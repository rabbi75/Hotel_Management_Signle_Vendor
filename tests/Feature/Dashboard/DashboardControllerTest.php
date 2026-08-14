<?php

declare(strict_types=1);

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Dashboard\Widgets\WidgetRegistry;
use App\Modules\User\Models\User;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('dashboard'))->assertRedirect(route('login'));
});

it('forbids a member without dashboard.view', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('dashboard'), inertiaHeaders())
        ->assertForbidden();
});

it('renders the dashboard with widgets the user may see', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view'], $company)->refresh();

    $response = actingAsMember($member, $company)
        ->get(route('dashboard'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'dashboard/index')
        ->assertJsonPath('props.can.customize', false)
        ->assertJsonPath('props.channel', 'company.'.$company->id);

    $keys = collect($response->json('props.widgets'))->pluck('key')->all();

    expect($keys)->toContain('stats-overview')
        ->and($keys)->toContain('quick-actions')
        ->and($keys)->toContain('tasks')
        // Gated behind audit permissions this member does not hold.
        ->and($keys)->not->toContain('recent-activity')
        ->and($keys)->not->toContain('recent-logins');
});

it('exposes every widget to a user holding all the permissions', function (): void {
    $company = workspace();
    $member = memberWith([
        'dashboard.view',
        'companies.members.view',
        'audit.activity.view',
        'audit.login.view',
    ], $company)->refresh();

    $response = actingAsMember($member, $company)
        ->get(route('dashboard'), inertiaHeaders())
        ->assertOk();

    $keys = collect($response->json('props.widgets'))->pluck('key')->all();

    expect($keys)->toEqualCanonicalizing(app(WidgetRegistry::class)->keys());
});

it('counts only members of the active workspace in the stats widget', function (): void {
    $owner = User::factory()->create();
    $company = workspace($owner);
    $other = workspace();

    memberWith([], $company);
    memberWith([], $other);
    memberWith([], $other);

    $viewer = memberWith(['dashboard.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->get(route('dashboard'), inertiaHeaders())
        ->assertOk();

    $stats = collect($response->json('props.widgets'))->firstWhere('key', 'stats-overview');
    $members = collect($stats['data']['stats'])->firstWhere('key', 'members');

    // Owner + one member + the viewer; nobody from the other workspace.
    expect($members['value'])->toBe(3);
});

it('flags the revenue widget as unavailable while billing is off', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view'], $company)->refresh();

    $response = actingAsMember($member, $company)
        ->get(route('dashboard'), inertiaHeaders())
        ->assertOk();

    $revenue = collect($response->json('props.widgets'))->firstWhere('key', 'revenue-chart');

    expect($revenue['data']['available'])->toBeFalse()
        ->and($revenue['data']['series'])->toBe([])
        ->and($revenue['data']['reason'])->not->toBeNull();
});

it('reports member growth for the active workspace only', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view', 'companies.members.view'], $company, CompanyRole::Member)->refresh();

    $response = actingAsMember($member, $company)
        ->get(route('dashboard'), inertiaHeaders())
        ->assertOk();

    $growth = collect($response->json('props.widgets'))->firstWhere('key', 'user-growth-chart');

    expect($growth['data']['series'])->toHaveCount(12)
        ->and($growth['data']['cumulative'])->toBe(2);
});
