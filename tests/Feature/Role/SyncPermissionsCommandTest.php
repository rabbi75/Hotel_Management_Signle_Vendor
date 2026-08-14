<?php

declare(strict_types=1);

use App\Modules\Role\Models\Permission;
use App\Modules\Role\Models\Role;

use function Pest\Laravel\artisan;

it('creates every declared permission and role', function (): void {
    artisan('permission:sync')->assertSuccessful();

    expect(Permission::query()->count())->toBe(count(Permission::declared()))
        ->and(Role::query()->count())->toBe(count((array) config('permissions.roles')));
});

it('expands group wildcards', function (): void {
    artisan('permission:sync')->assertSuccessful();

    $admin = Role::findByName('admin', 'web');

    expect($admin->permissions->pluck('name'))->toContain('users.create', 'roles.delete');
});

it('is idempotent', function (): void {
    artisan('permission:sync')->assertSuccessful();
    artisan('permission:sync')->assertSuccessful();

    expect(Permission::query()->count())->toBe(count(Permission::declared()));
});

it('reports orphans but keeps them unless pruning', function (): void {
    artisan('permission:sync')->assertSuccessful();

    Permission::create(['name' => 'legacy.thing', 'guard_name' => 'web']);

    artisan('permission:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'legacy.thing')->exists())->toBeTrue();

    artisan('permission:sync --prune')->assertSuccessful();

    expect(Permission::query()->where('name', 'legacy.thing')->exists())->toBeFalse();
});
