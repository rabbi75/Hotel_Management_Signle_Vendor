<?php

declare(strict_types=1);

use App\Modules\Role\Models\Role;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

// Roles may only be granted permissions that exist, so the registry is synced
// exactly as it is on a real deployment.
beforeEach(fn () => artisan('permission:sync'));

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('roles.index'))->assertRedirect(route('login'));
});

it('forbids a member without the view permission', function (): void {
    $company = workspace();
    $member = memberWith([], $company);

    actingAsMember($member, $company)
        ->get(route('roles.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists roles with their user and permission counts', function (): void {
    $company = workspace();
    $viewer = memberWith(['roles.view'], $company);

    Role::findOrCreate('editor', 'web');

    actingAsMember($viewer, $company)
        ->get(route('roles.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'roles/index')
        ->assertJsonStructure(['props' => ['table' => ['rows' => [['name', 'users_count', 'permissions_count']]]]]);
});

it('creates a role with permissions', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.view', 'roles.create'], $company);

    actingAsMember($admin, $company)
        ->post(route('roles.store'), [
            'name' => 'auditor',
            'permissions' => ['users.view', 'audit.activity.view'],
        ])
        ->assertRedirect();

    $role = Role::findByName('auditor', 'web');

    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['users.view', 'audit.activity.view']);
});

it('rejects a role name that is not a slug', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.create'], $company);

    actingAsMember($admin, $company)
        ->post(route('roles.store'), ['name' => 'Not A Slug'])
        ->assertSessionHasErrors('name');
});

it('rejects an undeclared permission', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.create'], $company);

    actingAsMember($admin, $company)
        ->post(route('roles.store'), ['name' => 'auditor', 'permissions' => ['users.invent']])
        ->assertSessionHasErrors('permissions.0');
});

it('forbids creating a role without the create permission', function (): void {
    $company = workspace();
    $member = memberWith(['roles.view'], $company);

    actingAsMember($member, $company)
        ->post(route('roles.store'), ['name' => 'auditor'])
        ->assertForbidden();
});

it('never lets anyone edit the super admin role', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.view', 'roles.update'], $company);
    $role = Role::findOrCreate((string) config('permissions.super_admin_role'), 'web');

    actingAsMember($admin, $company)
        ->put(route('roles.update', $role), ['name' => 'hijacked'])
        ->assertForbidden();

    expect($role->refresh()->name)->toBe((string) config('permissions.super_admin_role'));
});

it('updates a role', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.view', 'roles.update'], $company);
    $role = Role::findOrCreate('auditor', 'web');

    actingAsMember($admin, $company)
        ->put(route('roles.update', $role), ['name' => 'auditor', 'permissions' => ['users.view']])
        ->assertRedirect(route('roles.index'));

    expect($role->refresh()->permissions->pluck('name')->all())->toBe(['users.view']);
});

it('refuses to delete a role that is still assigned', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.view', 'roles.delete'], $company);
    $role = Role::findOrCreate('auditor', 'web');

    $holder = memberWith([], $company);
    $holder->assignRole($role);

    session(['auth.password_confirmed_at' => time()]);

    actingAsMember($admin, $company)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();
});

it('deletes an unassigned role once the password is confirmed', function (): void {
    $company = workspace();
    $admin = memberWith(['roles.view', 'roles.delete'], $company);
    $role = Role::findOrCreate('auditor', 'web');

    session(['auth.password_confirmed_at' => time()]);

    actingAsMember($admin, $company)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::query()->where('name', 'auditor')->exists())->toBeFalse();
});
