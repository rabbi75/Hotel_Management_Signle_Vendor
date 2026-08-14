<?php

declare(strict_types=1);

use App\Modules\Company\Models\Company;
use App\Modules\Search\Services\SearchAggregator;
use App\Modules\User\Models\User;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('search.index', ['q' => 'anything']))->assertRedirect(route('login'));
});

it('returns grouped json for the command palette', function (): void {
    $company = workspace();
    $member = memberWith(['users.view'], $company)->refresh();

    User::factory()->create(['name' => 'Zephyrine Quill', 'email' => 'zephyrine@example.com'])
        ->companies()->attach($company->id, ['role' => 'member', 'joined_at' => now()]);

    $response = actingAsMember($member, $company)
        ->getJson(route('search.index', ['q' => 'Zephyrine']))
        ->assertOk()
        ->assertJsonStructure(['term', 'total', 'groups' => [['key', 'label', 'icon', 'items']]]);

    $users = collect($response->json('groups'))->firstWhere('key', 'users');

    expect($users)->not->toBeNull()
        ->and(collect($users['items'])->pluck('title'))->toContain('Zephyrine Quill');
});

it('returns nothing for a term shorter than the minimum', function (): void {
    $company = workspace();
    $member = memberWith(['users.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->getJson(route('search.index', ['q' => 'a']))
        ->assertOk()
        ->assertJsonPath('total', 0)
        ->assertJsonPath('groups', []);

    expect(SearchAggregator::MIN_TERM_LENGTH)->toBe(2);
});

it('never returns a user from another workspace', function (): void {
    $mine = workspace();
    $theirs = workspace();

    $member = memberWith(['users.view'], $mine)->refresh();

    $outsider = User::factory()->create(['name' => 'Barnaby Elsewhere']);
    $outsider->companies()->attach($theirs->id, ['role' => 'member', 'joined_at' => now()]);

    $response = actingAsMember($member, $mine)
        ->getJson(route('search.index', ['q' => 'Barnaby']))
        ->assertOk();

    $titles = collect($response->json('groups'))
        ->flatMap(fn (array $group): array => $group['items'])
        ->pluck('title')
        ->all();

    expect($titles)->not->toContain('Barnaby Elsewhere');
});

it('never returns a workspace the user does not belong to', function (): void {
    $mine = workspace();
    Company::factory()->create(['name' => 'Nonesuch Holdings']);

    $member = memberWith(['companies.view'], $mine)->refresh();

    $response = actingAsMember($member, $mine)
        ->getJson(route('search.index', ['q' => 'Nonesuch']))
        ->assertOk();

    expect($response->json('total'))->toBe(0);
});

it('omits a group the user lacks permission for', function (): void {
    $company = workspace();

    // No users.view, so the users provider must not run at all.
    $member = memberWith(['companies.view'], $company)->refresh();

    User::factory()->create(['name' => 'Zephyrine Quill'])
        ->companies()->attach($company->id, ['role' => 'member', 'joined_at' => now()]);

    $response = actingAsMember($member, $company)
        ->getJson(route('search.index', ['q' => 'Zephyrine']))
        ->assertOk();

    $keys = collect($response->json('groups'))->pluck('key')->all();

    expect($keys)->not->toContain('users');
});

it('never surfaces installation settings in tenant search', function (): void {
    // Installation settings moved to the operator console, so a workspace
    // member must not be able to discover their existence — let alone their
    // values — by typing a schema key into the app's own search.
    $company = workspace();
    $member = memberWith(['dashboard.view'], $company)->refresh();

    $response = actingAsMember($member, $company)
        ->getJson(route('search.index', ['q' => 'from_address']))
        ->assertOk();

    expect(collect($response->json('groups'))->firstWhere('key', 'settings'))->toBeNull()
        ->and($response->getContent())->not->toContain('From Address');
});

it('finds navigation pages without needing any permission', function (): void {
    $company = workspace();
    $member = memberWith(['dashboard.view'], $company)->refresh();

    $response = actingAsMember($member, $company)
        ->getJson(route('search.index', ['q' => 'Dashboard']))
        ->assertOk();

    $pages = collect($response->json('groups'))->firstWhere('key', 'pages');

    expect($pages)->not->toBeNull()
        ->and(collect($pages['items'])->pluck('title'))->toContain('Dashboard');
});

it('renders a full results page for a normal visit', function (): void {
    $company = workspace();
    $member = memberWith(['users.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('search.index', ['q' => 'Zephyrine']), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'search/index')
        ->assertJsonPath('props.results.term', 'Zephyrine')
        ->assertJsonPath('props.min_length', SearchAggregator::MIN_TERM_LENGTH);
});

it('rejects an over-long term', function (): void {
    $company = workspace();
    $member = memberWith(['users.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->getJson(route('search.index', ['q' => str_repeat('a', 101)]))
        ->assertStatus(422);
});
