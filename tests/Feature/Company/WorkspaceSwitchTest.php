<?php

declare(strict_types=1);

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;

use function Pest\Laravel\post;

it('redirects a guest to the login screen', function (): void {
    $company = workspace();

    post(route('companies.switch', $company))->assertRedirect(route('login'));
});

it('switches the active workspace and records it on the user', function (): void {
    $first = workspace();
    $second = workspace();

    $user = memberWith([], $first)->refresh();
    $second->members()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    actingAsMember($user, $first)
        ->from(route('companies.index'))
        ->post(route('companies.switch', $second))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas('success');

    expect(session(config('saas.workspace.session_key')))->toBe($second->id)
        ->and($user->fresh()?->current_company_id)->toBe($second->id);
});

it('rejects switching into a workspace the user does not belong to', function (): void {
    $mine = workspace();
    $theirs = workspace();

    $user = memberWith([], $mine)->refresh();

    actingAsMember($user, $mine)
        ->post(route('companies.switch', $theirs))
        ->assertForbidden();

    expect($user->fresh()?->current_company_id)->not->toBe($theirs->id);
});

it('rejects switching into a deactivated workspace', function (): void {
    $company = workspace();
    $inactive = Company::factory()->create(['is_active' => false]);

    $user = memberWith([], $company)->refresh();
    $inactive->members()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    actingAsMember($user, $company)
        ->post(route('companies.switch', $inactive))
        ->assertForbidden();
});

it('does not leak another workspace through the shared company prop', function (): void {
    $mine = workspace();
    $theirs = workspace();

    $user = memberWith(['companies.view'], $mine)->refresh();

    $response = actingAsMember($user, $mine)
        ->get(route('companies.index'), inertiaHeaders())
        ->assertOk();

    $companies = $response->json('props.auth.companies');

    expect($companies)->toHaveCount(1)
        ->and($companies[0]['uuid'])->toBe($mine->uuid);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and($theirs->uuid)->not->toBe($mine->uuid);
});
