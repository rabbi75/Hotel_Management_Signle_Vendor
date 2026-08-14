<?php

declare(strict_types=1);

use App\Modules\Company\Models\Department;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('departments.index'))->assertRedirect(route('login'));
});

it('forbids a member without companies.view from the index', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('departments.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only departments in the active workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $mine = Department::factory()->forCompany($company)->create(['name' => 'Engineering']);
    $theirs = Department::factory()->forCompany($other)->create(['name' => 'Legal']);

    $viewer = memberWith(['companies.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->get(route('departments.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'departments/index');

    $names = collect($response->json('props.table.rows'))->pluck('name')->all();

    expect($names)->toContain($mine->name)
        ->and($names)->not->toContain($theirs->name);
});

it('creates a department', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.departments.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('departments.store'), ['name' => 'Engineering'])
        ->assertRedirect(route('departments.index'));

    $this->assertDatabaseHas('departments', [
        'company_id' => $company->id,
        'name' => 'Engineering',
        'slug' => 'engineering',
    ]);
});

it('rejects a department with no name', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.departments.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('departments.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('forbids creating a department without the manage permission', function (): void {
    $company = workspace();
    $member = memberWith(['companies.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('departments.store'), ['name' => 'Engineering'])
        ->assertForbidden();
});

it('will not let a department become its own parent', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.departments.manage'], $company)->refresh();
    $department = Department::factory()->forCompany($company)->create();

    actingAsMember($manager, $company)
        ->patch(route('departments.update', $department), [
            'name' => $department->name,
            'parent_id' => $department->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

it('will not let a department be moved beneath its own descendant', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.departments.manage'], $company)->refresh();

    $parent = Department::factory()->forCompany($company)->create(['name' => 'Group']);
    $child = Department::factory()->forCompany($company)->create(['name' => 'Unit', 'parent_id' => $parent->id]);

    actingAsMember($manager, $company)
        ->patch(route('departments.update', $parent), [
            'name' => $parent->name,
            'parent_id' => $child->id,
        ])
        ->assertSessionHasErrors('parent_id');

    expect($parent->fresh()?->parent_id)->toBeNull();
});

it('rejects a parent from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $manager = memberWith(['companies.view', 'companies.departments.manage'], $company)->refresh();
    $foreign = Department::factory()->forCompany($other)->create();

    actingAsMember($manager, $company)
        ->post(route('departments.store'), ['name' => 'Engineering', 'parent_id' => $foreign->id])
        ->assertSessionHasErrors('parent_id');
});

it('promotes children when a department is deleted', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.departments.manage'], $company)->refresh();

    $grandparent = Department::factory()->forCompany($company)->create();
    $parent = Department::factory()->forCompany($company)->create(['parent_id' => $grandparent->id]);
    $child = Department::factory()->forCompany($company)->create(['parent_id' => $parent->id]);

    actingAsMember($manager, $company)
        ->delete(route('departments.destroy', $parent))
        ->assertRedirect(route('departments.index'));

    expect($child->fresh()?->parent_id)->toBe($grandparent->id);
});

it('leaves the manager alone when the request does not carry manager_id', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.departments.manage'], $company)->refresh();
    $lead = memberWith([], $company);

    $department = Department::factory()->forCompany($company)->create(['manager_id' => $lead->id]);

    actingAsMember($manager, $company)
        ->patch(route('departments.update', $department), ['name' => 'Renamed'])
        ->assertRedirect();

    expect($department->fresh()?->manager_id)->toBe($lead->id)
        ->and($department->fresh()?->name)->toBe('Renamed');
});

it('clears the manager when manager_id is explicitly submitted as null', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.departments.manage'], $company)->refresh();
    $lead = memberWith([], $company);

    $department = Department::factory()->forCompany($company)->create(['manager_id' => $lead->id]);

    actingAsMember($manager, $company)
        ->patch(route('departments.update', $department), [
            'name' => $department->name,
            'manager_id' => null,
        ])
        ->assertRedirect();

    expect($department->fresh()?->manager_id)->toBeNull();
});

it('offers a member list on the create and edit screens', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.departments.manage'], $company)->refresh();
    $department = Department::factory()->forCompany($company)->create();

    $response = actingAsMember($manager, $company)
        ->get(route('departments.create'), inertiaHeaders())
        ->assertOk();

    expect(collect($response->json('props.members'))->pluck('value')->all())
        ->toContain((string) $company->owner_id)
        ->toContain((string) $manager->id);

    actingAsMember($manager, $company)
        ->get(route('departments.edit', $department), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount($company->members()->count(), 'props.members');
});

it('loads the counts the show screen renders', function (): void {
    $company = workspace();
    $viewer = memberWith(['companies.view'], $company)->refresh();

    $department = Department::factory()->forCompany($company)->create();
    Department::factory()->forCompany($company)->create(['parent_id' => $department->id]);

    actingAsMember($viewer, $company)
        ->get(route('departments.show', $department), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.department.children_count', 1)
        ->assertJsonPath('props.department.teams_count', 0);
});
