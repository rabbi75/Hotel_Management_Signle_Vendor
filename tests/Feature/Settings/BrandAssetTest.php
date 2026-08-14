<?php

declare(strict_types=1);

use App\Modules\Settings\Support\BrandAssetStore;
use App\Support\Branding\Branding;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The panel's three required fields. Every upload case posts these alongside
 * whatever it is actually testing, because the schema declares them required.
 */
function appearanceBase(): array
{
    return [
        'theme' => 'system',
        'primary_color' => '#2563eb',
        'sidebar_variant' => 'sidebar',
    ];
}

function storedAsset(string $slot): ?string
{
    $value = app(SettingsRepository::class)->getFrom(
        SettingsRepository::SCOPE_SYSTEM,
        null,
        "appearance.{$slot}_url",
    );

    return is_string($value) && $value !== '' ? $value : null;
}

/** The disk-relative path behind a stored URL. */
function assetPath(string $url): string
{
    return BrandAssetStore::DIRECTORY.'/'.basename($url);
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('stores an uploaded logo and records its url', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.appearance.update'), [
            ...appearanceBase(),
            'logo' => UploadedFile::fake()->image('logo.png', 320, 64),
        ])
        ->assertRedirect();

    $url = storedAsset('logo');

    expect($url)->not->toBeNull();
    Storage::disk('public')->assertExists(assetPath($url));
});

it('accepts an ico favicon, which the image rule alone would reject', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.appearance.update'), [
            ...appearanceBase(),
            'favicon' => UploadedFile::fake()->create('favicon.ico', 8, 'image/x-icon'),
        ])
        ->assertSessionHasNoErrors();

    expect(storedAsset('favicon'))->not->toBeNull();
});

it('refuses an svg, which would be script-bearing markup served from our own origin', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.appearance.update'), [
            ...appearanceBase(),
            'logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('logo');

    expect(storedAsset('logo'))->toBeNull();
});

it('refuses a non-image masquerading as one', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)
        ->put(route('admin.settings.appearance.update'), [
            ...appearanceBase(),
            'logo' => UploadedFile::fake()->create('logo.png', 4, 'text/plain'),
        ])
        ->assertSessionHasErrors('logo');
});

it('deletes the previous file when a logo is replaced', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)->put(route('admin.settings.appearance.update'), [
        ...appearanceBase(),
        'logo' => UploadedFile::fake()->image('first.png'),
    ]);

    $first = storedAsset('logo');

    actingAsAdmin($admin)->put(route('admin.settings.appearance.update'), [
        ...appearanceBase(),
        'logo' => UploadedFile::fake()->image('second.png'),
    ]);

    $second = storedAsset('logo');

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing(assetPath($first));
    Storage::disk('public')->assertExists(assetPath($second));
});

it('clears an asset and deletes its file when the remove flag is posted', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)->put(route('admin.settings.appearance.update'), [
        ...appearanceBase(),
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $url = storedAsset('logo');

    actingAsAdmin($admin)->put(route('admin.settings.appearance.update'), [
        ...appearanceBase(),
        'logo_cleared' => 1,
    ]);

    expect(storedAsset('logo'))->toBeNull();
    Storage::disk('public')->assertMissing(assetPath($url));
});

it('leaves an untouched asset alone when the rest of the panel is saved', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)->put(route('admin.settings.appearance.update'), [
        ...appearanceBase(),
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $url = storedAsset('logo');

    actingAsAdmin($admin)->put(route('admin.settings.appearance.update'), [
        ...appearanceBase(),
        'primary_color' => '#ff8800',
    ]);

    expect(storedAsset('logo'))->toBe($url);
    Storage::disk('public')->assertExists(assetPath($url));
});

it('never writes the upload fields themselves into the settings store', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)->put(route('admin.settings.appearance.update'), [
        ...appearanceBase(),
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $stored = app(SettingsRepository::class)->all(SettingsRepository::SCOPE_SYSTEM);

    expect($stored)->not->toHaveKey('appearance.logo')
        ->and($stored)->not->toHaveKey('appearance.logo_cleared');
});

it('exposes the uploaded assets through the branding service', function (): void {
    $admin = platformAdminWith(['platform.settings.general']);

    actingAsAdmin($admin)->put(route('admin.settings.appearance.update'), [
        ...appearanceBase(),
        'logo' => UploadedFile::fake()->image('logo.png'),
        'icon' => UploadedFile::fake()->image('icon.png'),
    ]);

    $branding = app(Branding::class)->toArray();

    expect($branding['logo'])->toBe(storedAsset('logo'))
        ->and($branding['icon'])->toBe(storedAsset('icon'))
        // An asset that was never uploaded reads as absent, not as an empty src.
        ->and($branding['landing_logo'])->toBeNull();
});

it('leaves an externally hosted url alone rather than trying to delete it', function (): void {
    Storage::disk('public')->put('branding/decoy.png', 'x');

    app(BrandAssetStore::class)->forget('https://cdn.example.com/branding/decoy.png');

    Storage::disk('public')->assertExists('branding/decoy.png');
});

it('refuses to delete outside its own directory', function (): void {
    Storage::disk('public')->put('private/keep.png', 'x');

    $prefix = Storage::disk('public')->url(BrandAssetStore::DIRECTORY).'/';

    app(BrandAssetStore::class)->forget($prefix.'../private/keep.png');

    Storage::disk('public')->assertExists('private/keep.png');
});
