<?php

declare(strict_types=1);

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Notifications\OwnershipTransferredNotification;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\post;

beforeEach(function (): void {
    Notification::fake();
});

it('redirects a guest to the login screen', function (): void {
    workspace();

    post(route('companies.transfer-ownership.store'))->assertRedirect(route('login'));
});

it('forbids a non-owner from transferring ownership', function (): void {
    $company = workspace();
    $member = memberWith(['companies.transfer'], $company)->refresh();
    $target = memberWith([], $company);

    actingAsMember($member, $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('companies.transfer-ownership.store'), ['user_id' => $target->id])
        ->assertForbidden();
});

it('requires a confirmed password', function (): void {
    $owner = User::factory()->create();
    $company = workspace($owner);
    $target = memberWith([], $company);

    actingAsMember($owner->refresh(), $company)
        ->post(route('companies.transfer-ownership.store'), ['user_id' => $target->id])
        ->assertRedirect(route('password.confirm'));
});

it('rejects a candidate who is not a member', function (): void {
    $owner = User::factory()->create();
    $company = workspace($owner);
    $outsider = User::factory()->create();

    actingAsMember($owner->refresh(), $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('companies.transfer-ownership.store'), ['user_id' => $outsider->id])
        ->assertSessionHasErrors('user_id');
});

it('swaps the two roles when ownership is transferred', function (): void {
    $owner = User::factory()->create();
    $company = workspace($owner);
    $successor = memberWith([], $company);

    actingAsMember($owner->refresh(), $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('companies.transfer-ownership.store'), ['user_id' => $successor->id])
        ->assertRedirect(route('companies.edit', $company));

    expect($company->fresh()?->owner_id)->toBe($successor->id);

    $this->assertDatabaseHas('company_user', [
        'company_id' => $company->id,
        'user_id' => $successor->id,
        'role' => CompanyRole::Owner->value,
    ]);

    $this->assertDatabaseHas('company_user', [
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => CompanyRole::Admin->value,
    ]);
});

it('notifies both parties', function (): void {
    $owner = User::factory()->create();
    $company = workspace($owner);
    $successor = memberWith([], $company);

    actingAsMember($owner->refresh(), $company)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('companies.transfer-ownership.store'), ['user_id' => $successor->id])
        ->assertRedirect();

    Notification::assertSentTo(
        [$owner, $successor],
        OwnershipTransferredNotification::class,
    );
});
