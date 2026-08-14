<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('settings.password.edit'))->assertRedirect(route('login'));
});

it('renders the password screen for any authenticated member', function (): void {
    $company = workspace();

    // Deliberately permission-less: changing your own password is not gated.
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('settings.password.edit'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'settings/password')
        ->assertJsonStructure(['props' => ['passwordChangedAt']]);
});

it('changes the password', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'Str0ng-New-Passphrase!',
            'password_confirmation' => 'Str0ng-New-Passphrase!',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('Str0ng-New-Passphrase!', (string) $member->refresh()->password))->toBeTrue();
});

it('rejects a wrong current password into the updatePassword bag', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('user-password.update'), [
            'current_password' => 'not-my-password',
            'password' => 'Str0ng-New-Passphrase!',
            'password_confirmation' => 'Str0ng-New-Passphrase!',
        ])
        ->assertSessionHasErrors('current_password', null, 'updatePassword');
});

it('rejects a confirmation that does not match', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'Str0ng-New-Passphrase!',
            'password_confirmation' => 'something-else',
        ])
        ->assertSessionHasErrors('password', null, 'updatePassword');
});
