<?php

declare(strict_types=1);

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Notification::fake();
});

it('redirects a guest to the login screen', function (): void {
    $company = workspace();

    get(route('companies.index'))->assertRedirect(route('login'));
    get(route('companies.edit', $company))->assertRedirect(route('login'));
});

it('forbids a member without companies.view from listing workspaces', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('companies.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only the workspaces the user belongs to', function (): void {
    $company = workspace();
    workspace();
    $member = memberWith(['companies.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('companies.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'companies/index')
        ->assertJsonCount(1, 'props.companies')
        ->assertJsonPath('props.companies.0.uuid', $company->uuid)
        ->assertJsonPath('props.companies.0.role', 'member');
});

it('creates a workspace and makes it active', function (): void {
    // CreateCompany grants the seeded `admin` role to the new owner.
    Role::findOrCreate('admin', 'web');

    $company = workspace();
    $member = memberWith(['companies.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('companies.store'), [
            'name' => 'Northwind',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'locale' => 'en',
        ])
        ->assertRedirect(route('dashboard'));

    $created = Company::query()->where('name', 'Northwind')->firstOrFail();

    expect($created->owner_id)->toBe($member->id)
        ->and($member->fresh()?->current_company_id)->toBe($created->id);

    $this->assertDatabaseHas('company_user', [
        'company_id' => $created->id,
        'user_id' => $member->id,
        'role' => 'owner',
    ]);
});

it('rejects a workspace with no name', function (): void {
    $company = workspace();
    $member = memberWith(['companies.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('companies.store'), ['name' => '', 'timezone' => 'UTC', 'currency' => 'USD', 'locale' => 'en'])
        ->assertSessionHasErrors('name');
});

it('refuses to create a workspace beyond the owned cap', function (): void {
    config(['saas.workspace.max_owned_per_user' => 1]);

    $owner = User::factory()->create();
    $company = workspace($owner);
    $owner->givePermissionTo(Permission::findOrCreate('companies.view', 'web'));
    $owner->flushPermissionCache();

    actingAsMember($owner->refresh(), $company)
        ->post(route('companies.store'), [
            'name' => 'One Too Many',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'locale' => 'en',
        ])
        ->assertForbidden();
});

it('updates the workspace profile', function (): void {
    $company = workspace();
    $member = memberWith(['companies.view', 'companies.update'], $company)->refresh();

    actingAsMember($member, $company)
        ->patch(route('companies.update', $company), [
            'name' => 'Renamed Co',
            'city' => 'Dhaka',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'locale' => 'en',
        ])
        ->assertRedirect();

    expect($company->fresh()?->name)->toBe('Renamed Co');
});

it('forbids a member without companies.update from editing the workspace', function (): void {
    $company = workspace();
    $member = memberWith(['companies.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->patch(route('companies.update', $company), [
            'name' => 'Nope',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'locale' => 'en',
        ])
        ->assertForbidden();
});

it('will not delete a workspace unless the typed name matches', function (): void {
    $owner = User::factory()->create();
    $company = workspace($owner);

    actingAsMember($owner->refresh(), $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('companies.destroy', $company), ['name' => 'Wrong Name'])
        ->assertSessionHasErrors('name');

    expect(Company::query()->whereKey($company->id)->exists())->toBeTrue();
});

it('lets the owner delete the workspace by typing its name', function (): void {
    $owner = User::factory()->create();
    $company = workspace($owner);

    actingAsMember($owner->refresh(), $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('companies.destroy', $company), ['name' => $company->name])
        ->assertRedirect(route('companies.index'));

    expect(Company::query()->whereKey($company->id)->exists())->toBeFalse();
});

it('forbids a non-owner from deleting the workspace', function (): void {
    $company = workspace();
    $member = memberWith(['companies.view', 'companies.delete'], $company)->refresh();

    actingAsMember($member, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('companies.destroy', $company), ['name' => $company->name])
        ->assertForbidden();
});
