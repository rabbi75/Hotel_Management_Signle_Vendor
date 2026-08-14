<?php

declare(strict_types=1);

use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\Media\Services\MediaLibraryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('saas.media.disk', 'public');
    Storage::fake('public');
});

it('forbids uploading without media.upload', function (): void {
    $company = workspace();
    $member = memberWith(['media.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('media.upload'), ['files' => [UploadedFile::fake()->image('photo.jpg')]])
        ->assertForbidden();
});

it('stores an uploaded image', function (): void {
    $company = workspace();
    $uploader = memberWith(['media.view', 'media.upload'], $company)->refresh();

    actingAsMember($uploader, $company)
        ->post(route('media.upload'), [
            'files' => [UploadedFile::fake()->image('brand-hero.jpg', 800, 600)],
        ])
        ->assertRedirect();

    $asset = MediaAsset::query()->first();

    expect($asset)->not->toBeNull()
        ->and($asset?->name)->toBe('brand-hero')
        ->and($asset?->company_id)->toBe($company->id)
        ->and($asset?->uploaded_by)->toBe($uploader->id)
        ->and($asset?->width)->toBe(800)
        ->and($asset?->file())->not->toBeNull();
});

it('rejects an upload larger than the configured ceiling', function (): void {
    config()->set('saas.media.max_upload_kb', 100);

    $company = workspace();
    $uploader = memberWith(['media.view', 'media.upload'], $company)->refresh();

    actingAsMember($uploader, $company)
        ->post(route('media.upload'), [
            'files' => [UploadedFile::fake()->create('huge.pdf', 5_000)],
        ])
        ->assertSessionHasErrors('files.0');

    expect(MediaAsset::query()->count())->toBe(0);
});

it('rejects a file type that is not allowed', function (): void {
    $company = workspace();
    $uploader = memberWith(['media.view', 'media.upload'], $company)->refresh();

    actingAsMember($uploader, $company)
        ->post(route('media.upload'), [
            'files' => [UploadedFile::fake()->create('payload.exe', 10)],
        ])
        ->assertSessionHas('error');

    expect(MediaAsset::query()->count())->toBe(0);
});

it('downscales an image past the configured longest edge', function (): void {
    config()->set('saas.media.max_image_dimension', 400);

    $company = workspace();
    $uploader = memberWith(['media.view', 'media.upload'], $company)->refresh();

    actingAsMember($uploader, $company)
        ->post(route('media.upload'), [
            'files' => [UploadedFile::fake()->image('oversized.jpg', 1200, 900)],
        ])
        ->assertRedirect();

    $asset = MediaAsset::query()->first();

    expect($asset?->width)->toBe(400)
        ->and($asset?->height)->toBe(300);
});

it('returns the existing asset instead of storing a duplicate', function (): void {
    $company = workspace();
    $uploader = memberWith(['media.view', 'media.upload'], $company)->refresh();
    $library = app(MediaLibraryService::class);

    // Held in a variable: the fake's temporary file is unlinked as soon as the
    // UploadedFile is garbage collected.
    $sample = UploadedFile::fake()->image('same.jpg', 100, 100);
    $contents = (string) file_get_contents((string) $sample->getRealPath());

    // Two distinct temporary files holding byte-identical content: the library
    // must key on the hash, not on the path or the name.
    $upload = static function (string $suffix) use ($contents): UploadedFile {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dup-'.$suffix.'.jpg';
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'same.jpg', 'image/jpeg', null, true);
    };

    $first = $library->upload($upload('a'), null, $uploader);
    $second = $library->upload($upload('b'), null, $uploader);

    expect($second->id)->toBe($first->id)
        ->and(MediaAsset::query()->count())->toBe(1);
});

it('rejects an upload into a folder from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $foreign = MediaFolder::factory()->forCompany($other)->create();
    $uploader = memberWith(['media.view', 'media.upload'], $company)->refresh();

    actingAsMember($uploader, $company)
        ->post(route('media.upload'), [
            'files' => [UploadedFile::fake()->image('photo.jpg')],
            'folder_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('folder_id');
});

it('produces a new version rather than overwriting when edited', function (): void {
    $company = workspace();
    $editor = memberWith(['media.view', 'media.upload', 'media.update'], $company)->refresh();

    $asset = app(MediaLibraryService::class)->upload(
        UploadedFile::fake()->image('editable.jpg', 800, 600),
        null,
        $editor,
    );

    actingAsMember($editor, $company)
        ->post(route('media.edit', $asset), ['width' => 200])
        ->assertRedirect();

    $version = MediaAsset::query()->where('original_asset_id', $asset->id)->first();

    expect($version)->not->toBeNull()
        ->and($version?->width)->toBe(200)
        ->and($asset->fresh()?->width)->toBe(800)
        ->and($asset->fresh()?->file())->not->toBeNull();
});

it('refuses to edit a non-image', function (): void {
    $company = workspace();
    $editor = memberWith(['media.view', 'media.update'], $company)->refresh();

    $asset = MediaAsset::factory()->forCompany($company)->document()->create();

    actingAsMember($editor, $company)
        ->post(route('media.edit', $asset), ['rotate' => 90])
        ->assertSessionHas('error');
});
