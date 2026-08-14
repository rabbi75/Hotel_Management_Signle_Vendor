<?php

declare(strict_types=1);

use App\Modules\Dashboard\Enums\WidgetSize;
use App\Modules\Dashboard\Services\DashboardService;
use App\Support\Settings\SettingsRepository;

use function Pest\Laravel\put;

it('redirects a guest to the login screen', function (): void {
    put(route('dashboard.layout.update'), ['layout' => []])->assertRedirect(route('login'));
});

it('forbids a member without dashboard.customize', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('dashboard.layout.update'), ['layout' => []])
        ->assertForbidden();
});

it('persists an ordered layout in the user settings scope', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view', 'dashboard.customize'], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('dashboard.layout.update'), [
            'layout' => [
                ['key' => 'tasks', 'size' => WidgetSize::Small->value],
                ['key' => 'stats-overview', 'size' => WidgetSize::Full->value],
            ],
        ])
        ->assertRedirect();

    $stored = app(SettingsRepository::class)->getFrom(
        SettingsRepository::SCOPE_USER,
        $member->id,
        DashboardService::LAYOUT_KEY,
    );

    expect($stored)->toBe([
        ['key' => 'tasks', 'size' => 'sm'],
        ['key' => 'stats-overview', 'size' => 'full'],
    ]);
});

it('renders the dashboard in the saved order', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view', 'dashboard.customize'], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('dashboard.layout.update'), [
            'layout' => [
                ['key' => 'tasks', 'size' => WidgetSize::Full->value],
                ['key' => 'stats-overview', 'size' => WidgetSize::Small->value],
            ],
        ]);

    $response = actingAsMember($member, $company)
        ->get(route('dashboard'), inertiaHeaders())
        ->assertOk();

    $widgets = collect($response->json('props.widgets'));
    $keys = $widgets->pluck('key')->all();

    // The two saved widgets lead, in the saved order; everything else follows.
    expect(array_slice($keys, 0, 2))->toBe(['tasks', 'stats-overview'])
        ->and($widgets->firstWhere('key', 'tasks')['size'])->toBe('full')
        ->and($widgets->firstWhere('key', 'stats-overview')['size'])->toBe('sm');
});

it('rejects an unknown widget key', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view', 'dashboard.customize'], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('dashboard.layout.update'), [
            'layout' => [['key' => 'not-a-widget', 'size' => 'md']],
        ])
        ->assertSessionHasErrors('layout.0.key');
});

it('rejects an unknown widget size', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view', 'dashboard.customize'], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('dashboard.layout.update'), [
            'layout' => [['key' => 'tasks', 'size' => 'enormous']],
        ])
        ->assertSessionHasErrors('layout.0.size');
});

it('resets a saved layout', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view', 'dashboard.customize'], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('dashboard.layout.update'), [
            'layout' => [['key' => 'tasks', 'size' => WidgetSize::Full->value]],
        ]);

    actingAsMember($member, $company)
        ->delete(route('dashboard.layout.destroy'))
        ->assertRedirect();

    $stored = app(SettingsRepository::class)->getFrom(
        SettingsRepository::SCOPE_USER,
        $member->id,
        DashboardService::LAYOUT_KEY,
    );

    expect($stored)->toBe([]);
});

it('keeps one user layout out of another user dashboard', function (): void {
    $company = workspace();
    $first = memberWith(['dashboard.view', 'dashboard.customize'], $company)->refresh();
    $second = memberWith(['dashboard.view', 'dashboard.customize'], $company)->refresh();

    actingAsMember($first, $company)
        ->put(route('dashboard.layout.update'), [
            'layout' => [['key' => 'tasks', 'size' => WidgetSize::Full->value]],
        ]);

    $stored = app(SettingsRepository::class)->getFrom(
        SettingsRepository::SCOPE_USER,
        $second->id,
        DashboardService::LAYOUT_KEY,
        [],
    );

    expect($stored)->toBe([]);
});
