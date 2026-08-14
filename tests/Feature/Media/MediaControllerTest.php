<?php

declare(strict_types=1);

use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('media.index'))->assertRedirect(route('login'));
});

it('forbids a member without media.view', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('media.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only assets in the active workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $mine = MediaAsset::factory()->forCompany($company)->create(['name' => 'Brand hero']);
    $theirs = MediaAsset::factory()->forCompany($other)->create(['name' => 'Competitor deck']);

    $viewer = memberWith(['media.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->get(route('media.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'media/index');

    $names = collect($response->json('props.assets.data'))->pluck('name')->all();

    expect($names)->toContain($mine->name)
        ->and($names)->not->toContain($theirs->name);
});

it('lists a folder only when that folder is selected', function (): void {
    $company = workspace();
    $folder = MediaFolder::factory()->forCompany($company)->create();

    $root = MediaAsset::factory()->forCompany($company)->create(['name' => 'At root']);
    $nested = MediaAsset::factory()->inFolder($folder)->create(['name' => 'In folder']);

    $viewer = memberWith(['media.view'], $company)->refresh();

    $atRoot = actingAsMember($viewer, $company)
        ->get(route('media.index'), inertiaHeaders())
        ->assertOk();

    expect(collect($atRoot->json('props.assets.data'))->pluck('name')->all())
        ->toContain($root->name)
        ->not->toContain($nested->name);

    $inFolder = actingAsMember($viewer, $company)
        ->get(route('media.index', ['folder' => $folder->id]), inertiaHeaders())
        ->assertOk();

    expect(collect($inFolder->json('props.assets.data'))->pluck('name')->all())
        ->toContain($nested->name)
        ->not->toContain($root->name);
});

it('filters by type', function (): void {
    $company = workspace();

    MediaAsset::factory()->forCompany($company)->create(['name' => 'A picture']);
    MediaAsset::factory()->forCompany($company)->document()->create(['name' => 'A contract']);

    $viewer = memberWith(['media.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->get(route('media.index', ['type' => 'document']), inertiaHeaders())
        ->assertOk();

    expect(collect($response->json('props.assets.data'))->pluck('name')->all())
        ->toContain('A contract')
        ->not->toContain('A picture');
});

it('searches across the whole library', function (): void {
    $company = workspace();
    $folder = MediaFolder::factory()->forCompany($company)->create();

    MediaAsset::factory()->inFolder($folder)->create(['name' => 'Needle in a folder']);

    $viewer = memberWith(['media.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->get(route('media.index', ['search' => 'Needle']), inertiaHeaders())
        ->assertOk();

    expect(collect($response->json('props.assets.data'))->pluck('name')->all())
        ->toContain('Needle in a folder');
});

it('updates only the metadata fields the request carries', function (): void {
    $company = workspace();
    $editor = memberWith(['media.view', 'media.update'], $company)->refresh();

    $asset = MediaAsset::factory()->forCompany($company)->create([
        'alt' => 'Original alt text',
        'caption' => 'Original caption',
    ]);

    actingAsMember($editor, $company)
        ->patch(route('media.update', $asset), ['name' => 'Renamed'])
        ->assertRedirect();

    expect($asset->fresh()?->name)->toBe('Renamed')
        ->and($asset->fresh()?->alt)->toBe('Original alt text')
        ->and($asset->fresh()?->caption)->toBe('Original caption');
});

it('clears a field when it is explicitly submitted as null', function (): void {
    $company = workspace();
    $editor = memberWith(['media.view', 'media.update'], $company)->refresh();

    $asset = MediaAsset::factory()->forCompany($company)->create(['alt' => 'Original alt text']);

    actingAsMember($editor, $company)
        ->patch(route('media.update', $asset), ['alt' => null])
        ->assertRedirect();

    expect($asset->fresh()?->alt)->toBeNull();
});

it('rejects a name longer than the column allows', function (): void {
    $company = workspace();
    $editor = memberWith(['media.view', 'media.update'], $company)->refresh();
    $asset = MediaAsset::factory()->forCompany($company)->create();

    actingAsMember($editor, $company)
        ->patch(route('media.update', $asset), ['name' => str_repeat('a', 300)])
        ->assertSessionHasErrors('name');
});

it('forbids editing an asset from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $foreign = MediaAsset::factory()->forCompany($other)->create();
    $editor = memberWith(['media.view', 'media.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->patch(route('media.update', $foreign), ['name' => 'Hijacked'])
        ->assertNotFound();

    expect($foreign->fresh()?->name)->not->toBe('Hijacked');
});

it('forbids deleting without media.delete', function (): void {
    $company = workspace();
    $member = memberWith(['media.view', 'media.update'], $company)->refresh();
    $asset = MediaAsset::factory()->forCompany($company)->create();

    actingAsMember($member, $company)
        ->delete(route('media.destroy', $asset))
        ->assertForbidden();
});

it('soft deletes an asset', function (): void {
    $company = workspace();
    $remover = memberWith(['media.view', 'media.delete'], $company)->refresh();
    $asset = MediaAsset::factory()->forCompany($company)->create();

    actingAsMember($remover, $company)
        ->delete(route('media.destroy', $asset))
        ->assertRedirect();

    $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
});

it('bulk moves a selection into a folder', function (): void {
    $company = workspace();
    $editor = memberWith(['media.view', 'media.update'], $company)->refresh();

    $folder = MediaFolder::factory()->forCompany($company)->create();
    $assets = MediaAsset::factory()->forCompany($company)->count(3)->create();

    actingAsMember($editor, $company)
        ->post(route('media.bulk'), [
            'action' => 'move',
            'ids' => $assets->pluck('id')->all(),
            'folder_id' => $folder->id,
        ])
        ->assertRedirect();

    expect(MediaAsset::query()->where('folder_id', $folder->id)->count())->toBe(3);
});

it('will not bulk act on assets from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $foreign = MediaAsset::factory()->forCompany($other)->create();
    $editor = memberWith(['media.view', 'media.delete'], $company)->refresh();

    actingAsMember($editor, $company)
        ->post(route('media.bulk'), ['action' => 'delete', 'ids' => [$foreign->id]])
        ->assertSessionHasErrors('ids.0');

    $this->assertDatabaseHas('media_assets', ['id' => $foreign->id, 'deleted_at' => null]);
});

it('serves the picker api scoped to the workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $mine = MediaAsset::factory()->forCompany($company)->create(['name' => 'Mine']);
    MediaAsset::factory()->forCompany($other)->create(['name' => 'Theirs']);

    $viewer = memberWith(['media.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->getJson(route('api.media.picker'))
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toBe([$mine->name]);
});

it('forbids the picker api without media.view', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->getJson(route('api.media.picker'))
        ->assertForbidden();
});
