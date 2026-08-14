<?php

declare(strict_types=1);

use App\Modules\Company\Actions\CreateCompany;
use App\Modules\Company\DTOs\CompanyData;
use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Actions\CreateWorkspace;
use App\Modules\Workspace\DTOs\WorkspaceData;
use App\Modules\Workspace\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspace;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function operationalWorkspacePermissions(): void
{
    foreach (['operations.workspaces.view', 'operations.workspaces.manage', 'operations.workspaces.switch'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
}

function makeHotel(Company $company, Workspace $workspace, string $name): Hotel
{
    $hotel = new Hotel([
        'name' => $name,
        'status' => HotelStatus::Active,
        'is_active' => true,
    ]);
    $hotel->company_id = $company->id;
    $hotel->workspace_id = $workspace->id;
    $hotel->save();

    return $hotel;
}

it('scopes hotel queries to the active operational workspace', function (): void {
    operationalWorkspacePermissions();

    $company = workspace();
    $owner = memberWith(['operations.workspaces.manage'], $company, CompanyRole::Owner);

    actingAsMember($owner, $company);

    $default = Workspace::query()->where('company_id', $company->id)->where('is_default', true)->firstOrFail();

    app(CurrentWorkspace::class)->set($default);

    $hotelA = makeHotel($company, $default, 'Alpha Hotel');

    $workspaceB = app(CreateWorkspace::class)->handle(
        $company,
        new WorkspaceData(name: 'Regional Ops'),
        $owner,
    );

    $hotelB = makeHotel($company, $workspaceB, 'Beta Hotel');

    app(CurrentWorkspace::class)->set($default);

    expect(Hotel::query()->pluck('id')->all())->toBe([$hotelA->id]);

    app(CurrentWorkspace::class)->set($workspaceB);

    expect(Hotel::query()->pluck('id')->all())->toBe([$hotelB->id]);
});

it('creates a default operational workspace when a tenant is created', function (): void {
    Role::findOrCreate('admin', 'web');

    $owner = User::factory()->create();

    $company = app(CreateCompany::class)->handle(
        new CompanyData(name: 'New Tenant Org'),
        $owner,
    );

    $default = Workspace::query()
        ->withoutCompanyScope()
        ->where('company_id', $company->id)
        ->where('is_default', true)
        ->first();

    expect($default)->not->toBeNull()
        ->and($default->name)->toBe(config('saas.operations.default_name'))
        ->and($default->members()->whereKey($owner->id)->exists())->toBeTrue();
});

it('switches operational workspace via route', function (): void {
    operationalWorkspacePermissions();

    $company = workspace();
    $owner = memberWith(['operations.workspaces.switch'], $company, CompanyRole::Owner);

    actingAsMember($owner, $company);

    $default = Workspace::query()->where('company_id', $company->id)->where('is_default', true)->firstOrFail();

    $second = app(CreateWorkspace::class)->handle(
        $company,
        new WorkspaceData(name: 'Second workspace'),
        $owner,
    );

    session([config('saas.operations.session_key') => $default->id]);

    test()->post(route('operational-workspaces.switch', $second))->assertRedirect();

    expect(session(config('saas.operations.session_key')))->toBe($second->id);
});
