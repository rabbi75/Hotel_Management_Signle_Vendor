<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\User\Http\Controllers\UserImpersonationController;

use function Pest\Laravel\post;

it('redirects a guest away from impersonation', function (): void {
    $company = workspace();
    $target = memberWith([], $company);

    post(route('users.impersonate', $target))->assertRedirect(route('login'));
});

it('forbids impersonation without the permission', function (): void {
    $company = workspace();
    $actor = memberWith(['users.view'], $company);
    $target = memberWith([], $company);

    actingAsMember($actor, $company)
        ->post(route('users.impersonate', $target))
        ->assertForbidden();
});

it('starts an impersonation and remembers the original identity', function (): void {
    $company = workspace();
    $actor = memberWith(['users.impersonate'], $company);
    $target = memberWith([], $company);

    actingAsMember($actor, $company)
        ->post(route('users.impersonate', $target))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas(UserImpersonationController::SESSION_KEY, $actor->id);

    expect(auth()->id())->toBe($target->id);

    expect(SecurityLog::query()->where('event', SecurityEvent::ImpersonationStarted->value)->exists())->toBeTrue();
});

it('refuses to impersonate a super admin', function (): void {
    $company = workspace();
    $actor = memberWith(['users.impersonate'], $company);
    $target = superAdmin($company);

    actingAsMember($actor, $company)
        ->post(route('users.impersonate', $target))
        ->assertForbidden();
});

it('refuses to impersonate yourself', function (): void {
    $company = workspace();
    $actor = memberWith(['users.impersonate'], $company);

    actingAsMember($actor, $company)
        ->post(route('users.impersonate', $actor))
        ->assertForbidden();
});

it('ends an impersonation and restores the original identity', function (): void {
    $company = workspace();
    $actor = memberWith(['users.impersonate'], $company);
    $target = memberWith([], $company);

    actingAsMember($actor, $company)->post(route('users.impersonate', $target));

    $this->delete(route('users.impersonate.stop'))
        ->assertRedirect(route('users.index'))
        ->assertSessionMissing(UserImpersonationController::SESSION_KEY);

    expect(auth()->id())->toBe($actor->id);

    expect(SecurityLog::query()->where('event', SecurityEvent::ImpersonationStopped->value)->exists())->toBeTrue();
});

it('rejects ending an impersonation that never started', function (): void {
    $company = workspace();
    $actor = memberWith(['users.impersonate'], $company);

    actingAsMember($actor, $company)
        ->delete(route('users.impersonate.stop'))
        ->assertForbidden();
});
