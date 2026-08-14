<?php

declare(strict_types=1);

use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\Media\Services\MediaLibraryService;

use function Pest\Laravel\post;

it('redirects a guest to the login screen', function (): void {
    workspace();

    post(route('media.folders.store'), ['name' => 'Brand'])->assertRedirect(route('login'));
});

it('forbids creating a folder without media.folders.manage', function (): void {
    $company = workspace();
    $member = memberWith(['media.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('media.folders.store'), ['name' => 'Brand'])
        ->assertForbidden();
});

it('creates a folder with a materialised path', function (): void {
    $company = workspace();
    $manager = memberWith(['media.view', 'media.folders.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('media.folders.store'), ['name' => 'Brand Assets'])
        ->assertRedirect();

    $this->assertDatabaseHas('media_folders', [
        'company_id' => $company->id,
        'name' => 'Brand Assets',
        'slug' => 'brand-assets',
        'path' => 'brand-assets',
    ]);
});

it('nests a folder beneath its parent', function (): void {
    $company = workspace();
    $manager = memberWith(['media.view', 'media.folders.manage'], $company)->refresh();
    $parent = MediaFolder::factory()->forCompany($company)->create(['name' => 'Brand', 'slug' => 'brand', 'path' => 'brand']);

    actingAsMember($manager, $company)
        ->post(route('media.folders.store'), ['name' => 'Logos', 'parent_id' => $parent->id])
        ->assertRedirect();

    $this->assertDatabaseHas('media_folders', ['path' => 'brand/logos']);
});

it('rejects a folder with no name', function (): void {
    $company = workspace();
    $manager = memberWith(['media.view', 'media.folders.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('media.folders.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('rejects a parent from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $foreign = MediaFolder::factory()->forCompany($other)->create();
    $manager = memberWith(['media.view', 'media.folders.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('media.folders.store'), ['name' => 'Sneaky', 'parent_id' => $foreign->id])
        ->assertSessionHasErrors('parent_id');
});

it('will not let a folder become its own parent', function (): void {
    $company = workspace();
    $manager = memberWith(['media.view', 'media.folders.manage'], $company)->refresh();
    $folder = MediaFolder::factory()->forCompany($company)->create();

    actingAsMember($manager, $company)
        ->patch(route('media.folders.update', $folder), ['parent_id' => $folder->id])
        ->assertSessionHasErrors('parent_id');

    expect($folder->fresh()?->parent_id)->toBeNull();
});

it('will not let a folder become its own descendant', function (): void {
    $company = workspace();
    $manager = memberWith(['media.view', 'media.folders.manage'], $company)->refresh();

    $library = app(MediaLibraryService::class);
    $parent = $library->createFolder('Brand');
    $child = $library->createFolder('Logos', $parent);
    $grandchild = $library->createFolder('Dark', $child);

    actingAsMember($manager, $company)
        ->patch(route('media.folders.update', $parent), ['parent_id' => $grandchild->id])
        ->assertSessionHasErrors('parent_id');

    expect($parent->fresh()?->parent_id)->toBeNull()
        ->and($parent->fresh()?->path)->toBe('brand');
});

it('rejects a cyclic move at the service level too', function (): void {
    workspace();

    $library = app(MediaLibraryService::class);
    $parent = $library->createFolder('Brand');
    $child = $library->createFolder('Logos', $parent);

    $library->moveFolder($parent, $child);
})->throws(MediaException::class, 'cannot be moved inside itself');

it('repaths descendants when a folder is renamed', function (): void {
    $company = workspace();
    $manager = memberWith(['media.view', 'media.folders.manage'], $company)->refresh();

    $library = app(MediaLibraryService::class);
    $parent = $library->createFolder('Brand');
    $child = $library->createFolder('Logos', $parent);

    actingAsMember($manager, $company)
        ->patch(route('media.folders.update', $parent), ['name' => 'Identity'])
        ->assertRedirect();

    expect($parent->fresh()?->path)->toBe('identity')
        ->and($child->fresh()?->path)->toBe('identity/logos');
});

it('refuses to delete a folder that still has contents', function (): void {
    $company = workspace();
    $manager = memberWith(['media.view', 'media.folders.manage'], $company)->refresh();

    $folder = MediaFolder::factory()->forCompany($company)->create();
    MediaAsset::factory()->inFolder($folder)->create();

    actingAsMember($manager, $company)
        ->delete(route('media.folders.destroy', $folder))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('media_folders', ['id' => $folder->id]);
});

it('deletes an empty folder', function (): void {
    $company = workspace();
    $manager = memberWith(['media.view', 'media.folders.manage'], $company)->refresh();
    $folder = MediaFolder::factory()->forCompany($company)->create();

    actingAsMember($manager, $company)
        ->delete(route('media.folders.destroy', $folder))
        ->assertRedirect();

    $this->assertDatabaseMissing('media_folders', ['id' => $folder->id]);
});

it('will not touch a folder from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $foreign = MediaFolder::factory()->forCompany($other)->create();
    $manager = memberWith(['media.view', 'media.folders.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->delete(route('media.folders.destroy', $foreign))
        ->assertNotFound();

    $this->assertDatabaseHas('media_folders', ['id' => $foreign->id]);
});
