<?php

declare(strict_types=1);

use App\Modules\CMS\Enums\PageStatus;
use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Models\PageBlock;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('cms.pages.index'))->assertRedirect(route('login'));
});

it('forbids a member without cms.pages.view', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('cms.pages.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only pages in the active workspace', function (): void {
    $company = workspace();
    $other = workspace();

    Page::factory()->forCompany($company)->create(['title' => 'About us', 'slug' => 'about-us']);
    Page::factory()->forCompany($other)->create(['title' => 'Their page', 'slug' => 'their-page']);

    $viewer = memberWith(['cms.pages.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->get(route('cms.pages.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'cms/pages/index');

    $titles = collect($response->json('props.table.rows'))->pluck('title')->all();

    expect($titles)->toContain('About us')
        ->and($titles)->not->toContain('Their page');
});

it('creates a page', function (): void {
    $company = workspace();
    $editor = memberWith(['cms.pages.view', 'cms.pages.create'], $company)->refresh();

    actingAsMember($editor, $company)
        ->post(route('cms.pages.store'), ['title' => 'Pricing overview'])
        ->assertRedirect();

    $this->assertDatabaseHas('pages', [
        'company_id' => $company->id,
        'title' => 'Pricing overview',
        'slug' => 'pricing-overview',
        'status' => PageStatus::Draft->value,
    ]);
});

it('rejects a page with no title', function (): void {
    $company = workspace();
    $editor = memberWith(['cms.pages.view', 'cms.pages.create'], $company)->refresh();

    actingAsMember($editor, $company)
        ->post(route('cms.pages.store'), ['title' => ''])
        ->assertSessionHasErrors('title');
});

it('rejects a slug that collides with a reserved application route', function (): void {
    $company = workspace();
    $editor = memberWith(['cms.pages.view', 'cms.pages.create'], $company)->refresh();

    foreach (['dashboard', 'settings', 'users'] as $reserved) {
        actingAsMember($editor, $company)
            ->post(route('cms.pages.store'), ['title' => 'Nope', 'slug' => $reserved])
            ->assertSessionHasErrors('slug');
    }

    expect(Page::query()->count())->toBe(0);
});

it('rejects a slug already used in the same workspace', function (): void {
    $company = workspace();
    Page::factory()->forCompany($company)->create(['slug' => 'about']);
    $editor = memberWith(['cms.pages.view', 'cms.pages.create'], $company)->refresh();

    actingAsMember($editor, $company)
        ->post(route('cms.pages.store'), ['title' => 'About', 'slug' => 'about'])
        ->assertSessionHasErrors('slug');
});

it('forbids creating a page without the create permission', function (): void {
    $company = workspace();
    $member = memberWith(['cms.pages.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('cms.pages.store'), ['title' => 'Nope'])
        ->assertForbidden();
});

it('leaves fields the request omitted untouched', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create([
        'title' => 'Original',
        'seo' => ['title' => 'Kept', 'description' => 'Also kept'],
    ]);
    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->patch(route('cms.pages.update', $page), ['title' => 'Renamed'])
        ->assertRedirect();

    $fresh = $page->fresh();

    expect($fresh?->title)->toBe('Renamed')
        ->and($fresh?->seo)->toBe(['title' => 'Kept', 'description' => 'Also kept']);
});

it('cannot reach a page belonging to another workspace', function (): void {
    $company = workspace();
    $other = workspace();
    $foreign = Page::factory()->forCompany($other)->create();

    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->get(route('cms.pages.edit', $foreign), inertiaHeaders())
        ->assertNotFound();
});

it('duplicates a page and its blocks as a fresh draft', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->published()->create(['title' => 'Home', 'slug' => 'home']);

    $block = new PageBlock([
        'page_id' => $page->id,
        'type' => 'hero',
        'order' => 0,
        'data' => ['heading' => 'Welcome'],
        'is_visible' => true,
    ]);
    $block->company_id = $company->id;
    $block->save();

    $editor = memberWith(['cms.pages.view', 'cms.pages.create', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->post(route('cms.pages.duplicate', $page))
        ->assertRedirect();

    $copy = Page::query()->where('slug', 'home-2')->firstOrFail();

    expect($copy->status)->toBe(PageStatus::Draft)
        ->and($copy->blocks()->count())->toBe(1);
});

it('publishes and unpublishes a page', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $publisher = memberWith(['cms.pages.view', 'cms.pages.publish'], $company)->refresh();

    actingAsMember($publisher, $company)->post(route('cms.pages.publish', $page))->assertRedirect();
    expect($page->fresh()?->status)->toBe(PageStatus::Published);

    actingAsMember($publisher, $company)->delete(route('cms.pages.unpublish', $page))->assertRedirect();
    expect($page->fresh()?->status)->toBe(PageStatus::Draft);
});

it('forbids publishing without the publish permission', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->post(route('cms.pages.publish', $page))
        ->assertForbidden();
});
