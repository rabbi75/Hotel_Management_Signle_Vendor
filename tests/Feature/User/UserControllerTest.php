<?php

declare(strict_types=1);

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;

/*
| Inertia pages are requested as XHR (X-Inertia) so these assertions run against
| props and status codes: the React pages themselves live outside this module.
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function userPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Nina',
        'last_name' => 'Okafor',
        'email' => 'nina.okafor@example.com',
        'password' => 'sup3rsecret',
        'status' => UserStatus::Active->value,
        'company_role' => CompanyRole::Member->value,
        'timezone' => 'UTC',
        'locale' => 'en',
        ...$overrides,
    ];
}

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('users.index'))->assertRedirect(route('login'));
});

it('forbids a member without the view permission', function (): void {
    $company = workspace();
    $member = memberWith([], $company);

    actingAsMember($member, $company)
        ->get(route('users.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists the workspace members', function (): void {
    $company = workspace();
    $viewer = memberWith(['users.view'], $company);

    actingAsMember($viewer, $company)
        ->get(route('users.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'users/index')
        ->assertJsonStructure(['props' => ['table' => ['rows', 'columns', 'filters', 'meta']]]);
});

it('never exposes a user who only belongs to another workspace', function (): void {
    $workspaceA = workspace();
    $viewer = memberWith(['users.view'], $workspaceA);

    $workspaceB = workspace();
    $stranger = memberWith([], $workspaceB);

    $rows = actingAsMember($viewer, $workspaceA)
        ->get(route('users.index'), inertiaHeaders())
        ->assertOk()
        ->json('props.table.rows');

    expect(collect($rows)->pluck('email'))->not->toContain($stranger->email);

    actingAsMember($viewer, $workspaceA)
        ->get(route('users.show', $stranger), inertiaHeaders())
        ->assertForbidden();
});

it('forbids creating a user without the create permission', function (): void {
    $company = workspace();
    $member = memberWith(['users.view'], $company);

    actingAsMember($member, $company)
        ->post(route('users.store'), userPayload())
        ->assertForbidden();
});

it('creates a user and enrols them in the current workspace', function (): void {
    Notification::fake();

    $company = workspace();
    $creator = memberWith(['users.view', 'users.create'], $company);

    actingAsMember($creator, $company)
        ->post(route('users.store'), userPayload(['email' => 'nina@example.com']))
        ->assertRedirect();

    $created = User::query()->where('email', 'nina@example.com')->sole();

    expect($created->status)->toBe(UserStatus::Active)
        ->and($created->belongsToCompany($company))->toBeTrue();
});

it('rejects an invalid payload', function (): void {
    $company = workspace();
    $creator = memberWith(['users.create'], $company);

    actingAsMember($creator, $company)
        ->post(route('users.store'), userPayload(['email' => 'not-an-email', 'first_name' => '']))
        ->assertSessionHasErrors(['email', 'first_name']);
});

it('rejects a duplicate email address', function (): void {
    $company = workspace();
    $creator = memberWith(['users.create'], $company);
    $existing = memberWith([], $company);

    actingAsMember($creator, $company)
        ->post(route('users.store'), userPayload(['email' => $existing->email]))
        ->assertSessionHasErrors('email');
});

it('updates a user', function (): void {
    $company = workspace();
    $editor = memberWith(['users.view', 'users.update'], $company);
    $target = memberWith([], $company);

    actingAsMember($editor, $company)
        ->put(route('users.update', $target), userPayload([
            'email' => $target->email,
            'first_name' => 'Renamed',
        ]))
        ->assertRedirect();

    expect($target->refresh()->first_name)->toBe('Renamed');
});

it('requires a confirmed password before deleting a user', function (): void {
    $company = workspace();
    $admin = memberWith(['users.delete'], $company);
    $target = memberWith([], $company);

    actingAsMember($admin, $company)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('password.confirm'));
});

it('soft deletes a user once the password is confirmed', function (): void {
    $company = workspace();
    $admin = memberWith(['users.delete'], $company);
    $target = memberWith([], $company);

    session(['auth.password_confirmed_at' => time()]);

    actingAsMember($admin, $company)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'));

    expect($target->refresh()->trashed())->toBeTrue();
});

it('forbids exporting without the export permission', function (): void {
    $company = workspace();
    $member = memberWith(['users.view'], $company);

    actingAsMember($member, $company)
        ->get(route('users.export', ['format' => 'csv']))
        ->assertForbidden();
});

it('exports the filtered listing as csv', function (): void {
    $company = workspace();
    $exporter = memberWith(['users.view', 'users.export'], $company);

    actingAsMember($exporter, $company)
        ->get(route('users.export', ['format' => 'csv']))
        ->assertOk()
        ->assertDownload();
});
