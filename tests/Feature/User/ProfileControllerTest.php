<?php

declare(strict_types=1);

use App\Modules\User\Models\User;
use App\Support\Enums\Theme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    get(route('profile.show'))->assertRedirect(route('login'));
});

it('shows the signed-in user their own profile', function (): void {
    $company = workspace();
    $user = memberWith([], $company);

    actingAsMember($user, $company)
        ->get(route('profile.show'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'profile/show')
        ->assertJsonPath('props.user.email', $user->email)
        ->assertJsonStructure(['props' => ['preferences' => ['timezone', 'locale', 'theme', 'notifications']]]);
});

it('updates the profile', function (): void {
    $company = workspace();
    $user = memberWith([], $company);

    actingAsMember($user, $company)
        ->from(route('profile.show'))
        ->put(route('profile.update'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => $user->email,
            'job_title' => 'Analyst',
        ])
        ->assertRedirect(route('profile.show'));

    expect($user->refresh()->name)->toBe('Ada Lovelace');
});

it('rejects an invalid profile payload', function (): void {
    $company = workspace();
    $user = memberWith([], $company);

    actingAsMember($user, $company)
        ->put(route('profile.update'), ['first_name' => '', 'last_name' => '', 'email' => 'nope'])
        ->assertSessionHasErrors(['first_name', 'last_name', 'email']);
});

it('updates preferences', function (): void {
    $company = workspace();
    $user = memberWith([], $company);

    actingAsMember($user, $company)
        ->from(route('profile.show'))
        ->put(route('profile.preferences.update'), [
            'timezone' => 'Europe/Berlin',
            'locale' => 'de',
            'theme' => Theme::Dark->value,
            'notifications' => ['mail_digest' => true],
        ])
        ->assertRedirect(route('profile.show'));

    $user->refresh();

    expect($user->timezone)->toBe('Europe/Berlin')
        ->and($user->theme)->toBe(Theme::Dark)
        ->and($user->preferences['notifications']['mail_digest'])->toBeTrue();
});

it('rejects an unsupported locale', function (): void {
    $company = workspace();
    $user = memberWith([], $company);

    actingAsMember($user, $company)
        ->put(route('profile.preferences.update'), [
            'timezone' => 'UTC',
            'locale' => 'kl',
            'theme' => Theme::System->value,
        ])
        ->assertSessionHasErrors('locale');
});

it('uploads and removes an avatar', function (): void {
    Storage::fake(config('media-library.disk_name'));

    $company = workspace();
    $user = memberWith([], $company);

    actingAsMember($user, $company)
        ->from(route('profile.show'))
        ->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg', 200, 200),
        ])
        ->assertRedirect(route('profile.show'));

    expect($user->refresh()->getMedia('avatar'))->toHaveCount(1);

    // Shared auth.user must be a flat array (not `{ data: … }`), or the
    // topbar avatar reads empty initials / a missing image URL.
    actingAsMember($user, $company)
        ->get(route('profile.show'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.auth.user.initials', $user->initials())
        ->assertJsonPath('props.auth.user.avatar', $user->avatarUrl())
        ->assertJsonPath('props.auth.user.avatar_url', $user->avatarUrl());

    actingAsMember($user, $company)
        ->delete(route('profile.avatar.destroy'))
        ->assertRedirect();

    expect($user->refresh()->getMedia('avatar'))->toHaveCount(0);
});

it('rejects an oversized avatar', function (): void {
    Storage::fake(config('media-library.disk_name'));

    $company = workspace();
    $user = memberWith([], $company);

    $tooBig = ((int) config('saas.uploads.avatar_max_size_kb')) + 512;

    actingAsMember($user, $company)
        ->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('huge.jpg')->size($tooBig),
        ])
        ->assertSessionHasErrors('avatar');
});

it('requires a confirmed password before deleting the account', function (): void {
    $company = workspace();
    $user = memberWith([], $company);

    actingAsMember($user, $company)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('password.confirm'));
});

it('refuses to delete an account that still owns a workspace', function (): void {
    $owner = User::factory()->create();
    $company = workspace($owner);

    session(['auth.password_confirmed_at' => time()]);

    actingAsMember($owner, $company)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect($owner->refresh()->trashed())->toBeFalse();
});

it('deletes the account of a member who owns nothing', function (): void {
    $company = workspace();
    $user = memberWith([], $company);

    session(['auth.password_confirmed_at' => time()]);

    actingAsMember($user, $company)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('home'));

    expect($user->refresh()->trashed())->toBeTrue();
});
