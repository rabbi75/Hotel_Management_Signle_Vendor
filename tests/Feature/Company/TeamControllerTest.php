<?php

declare(strict_types=1);

use App\Modules\Company\Models\Department;
use App\Modules\Company\Models\Team;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('teams.index'))->assertRedirect(route('login'));
});

it('forbids a member without companies.view from the index', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('teams.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only teams in the active workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $mine = Team::factory()->forCompany($company)->create(['name' => 'Platform']);
    $theirs = Team::factory()->forCompany($other)->create(['name' => 'Growth']);

    $viewer = memberWith(['companies.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->get(route('teams.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'teams/index');

    $names = collect($response->json('props.table.rows'))->pluck('name')->all();

    expect($names)->toContain($mine->name)
        ->and($names)->not->toContain($theirs->name);
});

it('creates a team with members', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.teams.manage'], $company)->refresh();
    $member = memberWith([], $company);

    actingAsMember($manager, $company)
        ->post(route('teams.store'), [
            'name' => 'Platform',
            'color' => '#2563eb',
            'member_ids' => [$member->id],
        ])
        ->assertRedirect(route('teams.index'));

    $team = Team::query()->where('name', 'Platform')->firstOrFail();

    expect($team->company_id)->toBe($company->id)
        ->and($team->members()->pluck('users.id')->all())->toBe([$member->id]);
});

it('rejects a team with no name', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.teams.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('teams.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('forbids creating a team without the manage permission', function (): void {
    $company = workspace();
    $member = memberWith(['companies.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('teams.store'), ['name' => 'Platform'])
        ->assertForbidden();
});

it('rejects a department from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $manager = memberWith(['companies.view', 'companies.teams.manage'], $company)->refresh();
    $foreign = Department::factory()->forCompany($other)->create();

    actingAsMember($manager, $company)
        ->post(route('teams.store'), ['name' => 'Platform', 'department_id' => $foreign->id])
        ->assertSessionHasErrors('department_id');
});

it('rejects a member from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $manager = memberWith(['companies.view', 'companies.teams.manage'], $company)->refresh();
    $outsider = memberWith([], $other);

    actingAsMember($manager, $company)
        ->post(route('teams.store'), ['name' => 'Platform', 'member_ids' => [$outsider->id]])
        ->assertSessionHasErrors('member_ids.0');
});

it('renames a team and refreshes its slug', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.teams.manage'], $company)->refresh();
    $team = Team::factory()->forCompany($company)->create(['name' => 'Old Name', 'slug' => 'old-name']);

    actingAsMember($manager, $company)
        ->patch(route('teams.update', $team), ['name' => 'New Name'])
        ->assertRedirect();

    expect($team->fresh()?->slug)->toBe('new-name');
});

it('will not touch a team from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $manager = memberWith(['companies.view', 'companies.teams.manage'], $company)->refresh();
    $foreign = Team::factory()->forCompany($other)->create();

    actingAsMember($manager, $company)
        ->patch(route('teams.update', $foreign), ['name' => 'Hijacked'])
        ->assertNotFound();

    expect($foreign->fresh()?->name)->not->toBe('Hijacked');
});

it('deletes a team and detaches its roster', function (): void {
    $company = workspace();
    $manager = memberWith(['companies.view', 'companies.teams.manage'], $company)->refresh();
    $member = memberWith([], $company);

    $team = Team::factory()->forCompany($company)->create();
    $team->members()->attach($member->id);

    actingAsMember($manager, $company)
        ->delete(route('teams.destroy', $team))
        ->assertRedirect(route('teams.index'));

    $this->assertDatabaseMissing('team_user', ['team_id' => $team->id, 'user_id' => $member->id]);
    expect(Team::query()->whereKey($team->id)->exists())->toBeFalse();
});
