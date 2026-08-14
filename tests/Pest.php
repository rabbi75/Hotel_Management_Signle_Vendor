<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\Platform\Database\Seeders\AdminRolePermissionSeeder;
use App\Modules\Platform\Models\Admin;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Actions\CreateDefaultWorkspace;
use App\Modules\Workspace\Models\Workspace;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

pest()->beforeEach(function (): void {
    test()->markTestSkipped('The SaaS operator console is disabled in single-vendor mode.');
})->in('Feature/Platform');

/*
|------------------------------------------------------------------------------
| Shared helpers
|------------------------------------------------------------------------------
|
| Nearly every feature test needs "a user, in a workspace, holding permissions".
| These helpers make that a single line so the test body only states what is
| actually under test.
|
*/

/**
 * Headers that make a test request look like a real Inertia visit.
 *
 * The version header is not optional: as soon as a Vite manifest exists on disk
 * (i.e. after anyone has run `npm run build`), Inertia answers a versionless GET
 * with a 409 asset-refresh instead of the page — so a suite that omits it passes
 * or fails depending on whether the assets happen to be built.
 *
 * @return array<string, string>
 */
function inertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ];
}

/**
 * Create a workspace owned by a (possibly new) user, and make it the active
 * tenant for the remainder of the test.
 */
function workspace(?User $owner = null, array $attributes = []): Company
{
    $owner ??= User::factory()->create();

    $company = Company::factory()->create([...$attributes, 'owner_id' => $owner->id]);

    $company->members()->attach($owner->id, [
        'role' => CompanyRole::Owner->value,
        'joined_at' => now(),
    ]);

    $defaultWorkspace = app(CreateDefaultWorkspace::class)->handle($company, $owner);

    app(CurrentCompany::class)->set($company);
    app(CurrentWorkspace::class)->set($defaultWorkspace);

    return $company;
}

/**
 * A user who belongs to $company and holds exactly $permissions.
 *
 * Passing no permissions produces a member who can reach the application but
 * is denied everything — the correct baseline for a 403 test.
 *
 * @param  list<string>  $permissions
 */
function memberWith(array $permissions = [], ?Company $company = null, CompanyRole $role = CompanyRole::Member): User
{
    $company ??= workspace();
    $user = User::factory()->create();

    $company->members()->attach($user->id, ['role' => $role->value, 'joined_at' => now()]);

    if ($permissions !== []) {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);
    }

    $user->flushPermissionCache();

    return $user;
}

function superAdmin(?Company $company = null): User
{
    $company ??= workspace();
    $user = User::factory()->create();

    $company->members()->attach($user->id, ['role' => CompanyRole::Owner->value, 'joined_at' => now()]);
    $user->assignRole(Role::findOrCreate(config('permissions.super_admin_role', 'super-admin'), 'web'));
    $user->flushPermissionCache();

    return $user;
}

/**
 * Act as a user with the workspace resolved, mirroring what the
 * SetCurrentCompany middleware does for a real request.
 */
function actingAsMember(User $user, ?Company $company = null): TestCase
{
    $company ??= $user->companies()->first();

    if ($company instanceof Company) {
        app(CurrentCompany::class)->set($company);
        session([config('saas.workspace.session_key') => $company->id]);

        $operational = Workspace::query()
            ->where('company_id', $company->id)
            ->where('is_default', true)
            ->first();

        if ($operational instanceof Workspace) {
            app(CurrentWorkspace::class)->set($operational);
            session([config('saas.operations.session_key') => $operational->id]);
        }
    }

    return test()->actingAs($user);
}

/**
 * A platform operator on the `admin` guard, holding the given admin role.
 *
 * Entirely separate from the tenant helpers above: this is who reaches /admin.
 */
function platformAdmin(string $role = 'super-admin'): Admin
{
    (new AdminRolePermissionSeeder)->run();

    $admin = Admin::factory()->create();
    $admin->assignRole($role);

    return $admin;
}

/**
 * An operator holding exactly the given permissions and nothing else.
 *
 * The counterpart to {@see memberWith()} for the console: it is how a test says
 * "this admin may read settings but not write mail" without inventing a role.
 *
 * @param  list<string>  $permissions
 */
function platformAdminWith(array $permissions = []): Admin
{
    (new AdminRolePermissionSeeder)->run();

    $admin = Admin::factory()->create();

    if ($permissions !== []) {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $admin->givePermissionTo($permissions);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $admin->refresh();
}

/**
 * Authenticate on the `admin` guard for a console request.
 *
 * Sets the admin guard's user but keeps `web` as the default guard, mirroring
 * production (where the default guard is never `admin`). Middleware that resolves
 * the request's default-guard user — SetCurrentCompany, shared by the API guard
 * too, so it cannot hardcode `web` — then behaves exactly as it does live.
 */
function actingAsAdmin(Admin $admin): TestCase
{
    return test()->actingAs($admin, 'admin');
}

/**
 * Buy a plan through the real two-step flow.
 *
 * `POST billing.subscribe` no longer creates anything: it opens a checkout and
 * hands back where to send the customer. The subscription is created on the
 * signed return leg, which is what this follows.
 */
function completeCheckout(User $buyer, Company $company, string $plan, string $interval = 'monthly', ?string $coupon = null): void
{
    $payload = ['plan' => $plan, 'interval' => $interval];

    if ($coupon !== null) {
        $payload['coupon'] = $coupon;
    }

    $start = actingAsMember($buyer, $company)->post(route('billing.subscribe'), $payload);

    // Inertia signals an external redirect with 409 + X-Inertia-Location.
    $target = $start->headers->get('X-Inertia-Location') ?? $start->headers->get('Location');

    expect($target)->not->toBeNull();

    actingAsMember($buyer, $company)->get($target);
}
