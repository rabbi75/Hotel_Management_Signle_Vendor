<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Models\User;

use function Pest\Laravel\post;

it('redirects a guest away from suspension', function (): void {
    $company = workspace();
    $target = memberWith([], $company);

    post(route('users.suspend', $target), ['reason' => 'Policy breach'])
        ->assertRedirect(route('login'));
});

it('forbids suspension without the suspend permission', function (): void {
    $company = workspace();
    $actor = memberWith(['users.view'], $company);
    $target = memberWith([], $company);

    actingAsMember($actor, $company)
        ->post(route('users.suspend', $target), ['reason' => 'Policy breach'])
        ->assertForbidden();
});

it('suspends a user and records a security event', function (): void {
    $company = workspace();
    $actor = memberWith(['users.suspend'], $company);
    $target = memberWith([], $company);

    actingAsMember($actor, $company)
        ->from(route('users.index'))
        ->post(route('users.suspend', $target), ['reason' => 'Repeated policy breach'])
        ->assertRedirect(route('users.index'));

    expect($target->refresh()->status)->toBe(UserStatus::Suspended)
        ->and($target->suspended_at)->not->toBeNull();

    expect(SecurityLog::query()->where('event', SecurityEvent::UserSuspended->value)->exists())->toBeTrue();
});

it('requires a reason for a suspension', function (): void {
    $company = workspace();
    $actor = memberWith(['users.suspend'], $company);
    $target = memberWith([], $company);

    actingAsMember($actor, $company)
        ->post(route('users.suspend', $target), ['reason' => ''])
        ->assertSessionHasErrors('reason');
});

it('refuses to let a user suspend themselves', function (): void {
    $company = workspace();
    $actor = memberWith(['users.suspend'], $company);

    actingAsMember($actor, $company)
        ->post(route('users.suspend', $actor), ['reason' => 'Locking myself out'])
        ->assertForbidden();
});

it('restores a suspended user', function (): void {
    $company = workspace();
    $actor = memberWith(['users.suspend'], $company);
    $target = memberWith([], $company);

    $target->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => now()])->save();

    actingAsMember($actor, $company)
        ->from(route('users.index'))
        ->post(route('users.restore', $target))
        ->assertRedirect(route('users.index'));

    expect($target->refresh()->status)->toBe(UserStatus::Active)
        ->and($target->suspended_at)->toBeNull();
});

it('permanently deletes a user once the password is confirmed', function (): void {
    $company = workspace();
    $actor = memberWith(['users.delete'], $company);
    $target = memberWith([], $company);
    $targetId = $target->id;

    session(['auth.password_confirmed_at' => time()]);

    actingAsMember($actor, $company)
        ->delete(route('users.force-delete', $target))
        ->assertRedirect(route('users.index'));

    expect(User::withTrashed()->whereKey($targetId)->exists())->toBeFalse();
});
