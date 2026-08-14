<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\Role\Models\Role;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

beforeEach(fn () => artisan('permission:sync'));

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('roles.permissions.show'))->assertRedirect(route('login'));
});

it('forbids the matrix without the update permission', function (): void {
    $company = workspace();
    $member = memberWith(['roles.view'], $company);

    actingAsMember($member, $company)
        ->get(route('roles.permissions.show'), inertiaHeaders())
        ->assertForbidden();
});

it('renders the registry crossed with every editable role', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.view', 'roles.update'], $company);

    Role::findOrCreate('auditor', 'web');

    actingAsMember($admin, $company)
        ->get(route('roles.permissions.show'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'roles/permissions')
        ->assertJsonStructure(['props' => [
            'groups' => [['key', 'label', 'permissions']],
            'roles' => [['id', 'name', 'label', 'permissions']],
        ]]);
});

it('persists the whole grid in one submission', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.view', 'roles.update'], $company);

    $auditor = Role::findOrCreate('auditor', 'web');
    $viewer = Role::findOrCreate('viewer', 'web');

    actingAsMember($admin, $company)
        ->from(route('roles.permissions.show'))
        ->put(route('roles.permissions.update'), [
            'matrix' => [
                $auditor->id => ['users.view', 'audit.activity.view'],
                $viewer->id => ['users.view'],
            ],
        ])
        ->assertRedirect(route('roles.permissions.show'));

    expect($auditor->refresh()->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['users.view', 'audit.activity.view'])
        ->and($viewer->refresh()->permissions->pluck('name')->all())->toBe(['users.view']);

    expect(SecurityLog::query()->where('event', SecurityEvent::PermissionsChanged->value)->exists())->toBeTrue();
});

it('rejects a permission outside the registry', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.update'], $company);
    $role = Role::findOrCreate('auditor', 'web');

    actingAsMember($admin, $company)
        ->put(route('roles.permissions.update'), ['matrix' => [$role->id => ['users.invent']]])
        ->assertSessionHasErrors("matrix.{$role->id}.0");
});

it('ignores the super admin role even when it is submitted', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.update'], $company);

    $superAdmin = Role::findOrCreate((string) config('permissions.super_admin_role'), 'web');

    actingAsMember($admin, $company)
        ->put(route('roles.permissions.update'), ['matrix' => [$superAdmin->id => ['users.view']]])
        ->assertRedirect();

    expect($superAdmin->refresh()->permissions)->toHaveCount(0);
});
