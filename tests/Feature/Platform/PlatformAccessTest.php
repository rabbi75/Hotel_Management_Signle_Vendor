<?php

declare(strict_types=1);

use App\Modules\Company\Models\Company;
use App\Modules\Platform\Models\Admin;

use function Pest\Laravel\get;

it('redirects a guest to the admin login', function (): void {
    get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

it('keeps a tenant user out of the console', function (): void {
    // Even a tenant super-admin has no console access — the guard is separate.
    $company = workspace();
    $owner = superAdmin($company);

    actingAsMember($owner, $company)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

it('admits an authenticated admin', function (): void {
    actingAsAdmin(platformAdmin())
        ->get(route('admin.dashboard'))
        ->assertOk();
});

it('shows the admin login to a guest', function (): void {
    get(route('admin.login'))->assertOk();
});

it('bounces an already-signed-in admin off the login', function (): void {
    actingAsAdmin(platformAdmin())
        ->get(route('admin.login'))
        ->assertRedirect(route('admin.dashboard'));
});

it('lets a support admin view tenants but not manage them', function (): void {
    $admin = platformAdmin('support');
    $company = workspace();
    $company->forceFill(['name' => 'Unrelated Ltd'])->save();

    actingAsAdmin($admin)->get(route('admin.tenants.index'))->assertOk();

    actingAsAdmin($admin)
        ->patch(route('admin.tenants.status', $company), ['is_active' => false])
        ->assertForbidden();
});

it('forbids a support admin from the admins roster', function (): void {
    actingAsAdmin(platformAdmin('support'))
        ->get(route('admin.admins.index'))
        ->assertForbidden();
});

it('lists workspaces the admin has no membership in', function (): void {
    $admin = platformAdmin();
    $other = workspace();

    actingAsAdmin($admin)->getJson(route('admin.tenants.index'))->assertOk();

    expect(Company::query()->whereKey($other->id)->exists())->toBeTrue();
});

it('lets a super admin create another admin', function (): void {
    actingAsAdmin(platformAdmin())
        ->post(route('admin.admins.store'), [
            'name' => 'Second Operator',
            'email' => 'second@yoursaas.test',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'role' => 'support',
        ])
        ->assertSessionHas('success');

    expect(Admin::query()->where('email', 'second@yoursaas.test')->exists())->toBeTrue();
});

it('refuses to let an admin deactivate themselves', function (): void {
    $admin = platformAdmin();

    actingAsAdmin($admin)
        ->patch(route('admin.admins.deactivate', $admin))
        ->assertSessionHas('error');

    expect($admin->fresh()->status)->toBe('active');
});
