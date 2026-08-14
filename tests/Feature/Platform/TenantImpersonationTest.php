<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\Platform\Actions\ImpersonateTenant;
use App\Modules\User\Models\User;

use function Pest\Laravel\delete;
use function Pest\Laravel\post;

use Spatie\Permission\Models\Role;

it('signs the admin into the tenant while keeping the console session', function (): void {
    $admin = platformAdmin();
    $owner = User::factory()->create();
    $tenant = workspace($owner);

    actingAsAdmin($admin)
        ->post(route('admin.tenants.impersonate', $tenant))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(ImpersonateTenant::ADMIN_SESSION_KEY, $admin->id)
        ->assertSessionHas((string) config('saas.workspace.session_key'), $tenant->id);

    // The web guard is now the tenant owner; the admin guard is untouched.
    expect(auth()->guard('web')->id())->toBe($owner->id);
    expect(auth()->guard('admin')->id())->toBe($admin->id);
    expect(SecurityLog::query()
        ->where('event', SecurityEvent::ImpersonationStarted->value)
        ->where('admin_id', $admin->id)
        ->exists())->toBeTrue();
});

it('returns to the console when the impersonation ends', function (): void {
    $admin = platformAdmin();
    $owner = User::factory()->create();
    $tenant = workspace($owner);

    actingAsAdmin($admin)->post(route('admin.tenants.impersonate', $tenant));

    delete(route('users.impersonate.stop'))
        ->assertRedirect(route('admin.tenants.show', $tenant));

    // Web guard dropped, admin guard intact.
    expect(auth()->guard('web')->check())->toBeFalse();
});

it('blocks a money-moving action while impersonating', function (): void {
    $admin = platformAdmin();
    $owner = User::factory()->create();
    $tenant = workspace($owner);

    actingAsAdmin($admin)->post(route('admin.tenants.impersonate', $tenant));

    post(route('billing.subscribe'))->assertForbidden();
});

it('forbids a support admin without the impersonate permission from... having it', function (): void {
    // support role includes impersonate, so this asserts the grant is present.
    expect(platformAdmin('support')->can('platform.tenants.impersonate'))->toBeTrue();
});

it('refuses to impersonate a workspace whose owner is a super admin', function (): void {
    $admin = platformAdmin();

    // A workspace whose *owner* is a web super-admin.
    Role::findOrCreate(config('permissions.super_admin_role', 'super-admin'), 'web');
    $owner = User::factory()->create();
    $owner->assignRole(config('permissions.super_admin_role', 'super-admin'));
    $tenant = workspace($owner);

    actingAsAdmin($admin)
        ->post(route('admin.tenants.impersonate', $tenant))
        ->assertForbidden();
});
