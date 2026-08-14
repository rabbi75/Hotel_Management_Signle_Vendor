<?php

declare(strict_types=1);

use App\Modules\User\Enums\UserStatus;

it('signs out a user who was suspended mid-session', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    $test = actingAsMember($user, $company);

    $user->forceFill([
        'status' => UserStatus::Suspended,
        'suspended_at' => now(),
        'suspended_reason' => 'Payment dispute',
    ])->save();

    $test->get(route('settings.sessions.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
});

it('lets an active user through', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)
        ->get(route('settings.sessions.index'), inertiaHeaders())
        ->assertOk();

    $this->assertAuthenticated();
});
