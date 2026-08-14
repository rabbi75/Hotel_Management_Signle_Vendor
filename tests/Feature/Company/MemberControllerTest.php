<?php

declare(strict_types=1);

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;

beforeEach(function (): void {
    Notification::fake();
});

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('companies.members.index'))->assertRedirect(route('login'));
});

it('forbids a member without companies.members.view', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('companies.members.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only members of the active workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $viewer = memberWith(['companies.members.view'], $company)->refresh();
    $outsider = memberWith([], $other);

    $response = actingAsMember($viewer, $company)
        ->get(route('companies.members.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'companies/members/index');

    $emails = collect($response->json('props.table.rows'))->pluck('email')->all();

    expect($emails)->toContain($viewer->email)
        ->and($emails)->not->toContain($outsider->email);
});

it('changes a member workspace role', function (): void {
    $company = workspace();
    $admin = memberWith(['companies.members.view', 'companies.members.update'], $company)->refresh();
    $target = memberWith([], $company);

    actingAsMember($admin, $company)
        ->patch(route('companies.members.update', $target->uuid), ['role' => CompanyRole::Admin->value])
        ->assertRedirect();

    $this->assertDatabaseHas('company_user', [
        'company_id' => $company->id,
        'user_id' => $target->id,
        'role' => CompanyRole::Admin->value,
    ]);
});

it('rejects an unknown workspace role', function (): void {
    $company = workspace();
    $admin = memberWith(['companies.members.view', 'companies.members.update'], $company)->refresh();
    $target = memberWith([], $company);

    actingAsMember($admin, $company)
        ->patch(route('companies.members.update', $target->uuid), ['role' => 'emperor'])
        ->assertSessionHasErrors('role');
});

it('will not let the owner be demoted', function (): void {
    $owner = User::factory()->create();
    $company = workspace($owner);
    $admin = memberWith(['companies.members.view', 'companies.members.update'], $company)->refresh();

    actingAsMember($admin, $company)
        ->patch(route('companies.members.update', $owner->uuid), ['role' => CompanyRole::Member->value])
        ->assertSessionHasErrors('role');

    $this->assertDatabaseHas('company_user', [
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => CompanyRole::Owner->value,
    ]);
});

it('will not let anyone be promoted to owner', function (): void {
    $company = workspace();
    $admin = memberWith(['companies.members.view', 'companies.members.update'], $company)->refresh();
    $target = memberWith([], $company);

    actingAsMember($admin, $company)
        ->patch(route('companies.members.update', $target->uuid), ['role' => CompanyRole::Owner->value])
        ->assertSessionHasErrors('role');
});

it('removes a member from the workspace', function (): void {
    $company = workspace();
    $admin = memberWith(['companies.members.view', 'companies.members.remove'], $company)->refresh();
    $target = memberWith([], $company);

    actingAsMember($admin, $company)
        ->delete(route('companies.members.destroy', $target->uuid))
        ->assertRedirect();

    $this->assertDatabaseMissing('company_user', [
        'company_id' => $company->id,
        'user_id' => $target->id,
    ]);
});

it('will not let the owner be removed', function (): void {
    $owner = User::factory()->create();
    $company = workspace($owner);
    $admin = memberWith(['companies.members.view', 'companies.members.remove'], $company)->refresh();

    actingAsMember($admin, $company)
        ->delete(route('companies.members.destroy', $owner->uuid))
        ->assertSessionHasErrors('user');

    $this->assertDatabaseHas('company_user', [
        'company_id' => $company->id,
        'user_id' => $owner->id,
    ]);
});

it('will not let a member remove themselves', function (): void {
    $company = workspace();
    $admin = memberWith(['companies.members.view', 'companies.members.remove'], $company)->refresh();

    actingAsMember($admin, $company)
        ->delete(route('companies.members.destroy', $admin->uuid))
        ->assertSessionHasErrors('user');

    $this->assertDatabaseHas('company_user', [
        'company_id' => $company->id,
        'user_id' => $admin->id,
    ]);
});

it('forbids removing a member without companies.members.remove', function (): void {
    $company = workspace();
    $viewer = memberWith(['companies.members.view'], $company)->refresh();
    $target = memberWith([], $company);

    actingAsMember($viewer, $company)
        ->delete(route('companies.members.destroy', $target->uuid))
        ->assertForbidden();
});
