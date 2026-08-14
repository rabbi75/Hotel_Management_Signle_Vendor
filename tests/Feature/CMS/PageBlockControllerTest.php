<?php

declare(strict_types=1);

use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Models\PageBlock;
use App\Modules\CMS\Services\BlockRegistry;

use function Pest\Laravel\post;

/**
 * `company_id` is guarded, so it is stamped after construction exactly as the
 * application does — the tenant hook only fills it for the *active* workspace.
 */
function blockOn(Page $page, string $type = 'hero', int $order = 0): PageBlock
{
    $block = new PageBlock([
        'page_id' => $page->id,
        'type' => $type,
        'order' => $order,
        'data' => [],
        'is_visible' => true,
    ]);
    $block->company_id = $page->company_id;
    $block->save();

    return $block;
}

it('redirects a guest adding a block', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();

    post(route('cms.pages.blocks.store', $page), ['type' => 'hero'])->assertRedirect(route('login'));
});

it('registers every configured block type', function (): void {
    $registry = app(BlockRegistry::class);

    /** @var list<string> $configured */
    $configured = config('saas.cms.block_types');

    expect($registry->types())->toEqualCanonicalizing($configured);
});

it('adds a block with its schema defaults', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->post(route('cms.pages.blocks.store', $page), ['type' => 'hero'])
        ->assertRedirect();

    $block = PageBlock::query()->where('page_id', $page->id)->firstOrFail();

    expect($block->type)->toBe('hero')
        ->and($block->data)->toHaveKey('heading')
        ->and($block->is_visible)->toBeTrue();
});

it('rejects an unknown block type', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->post(route('cms.pages.blocks.store', $page), ['type' => 'not-a-block'])
        ->assertSessionHasErrors('type');
});

it('forbids adding a block without the update permission', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $member = memberWith(['cms.pages.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('cms.pages.blocks.store', $page), ['type' => 'hero'])
        ->assertForbidden();
});

it('validates block data against the block schema', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $block = blockOn($page);
    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->patch(route('cms.blocks.update', $block), ['data' => ['heading' => '']])
        ->assertSessionHasErrors('data.heading');
});

it('stores only the fields the block schema declares', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $block = blockOn($page);
    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->patch(route('cms.blocks.update', $block), [
            'data' => ['heading' => 'Hello', 'not_a_field' => 'dropped'],
        ])
        ->assertRedirect();

    $data = $block->fresh()?->data ?? [];

    expect($data)->toHaveKey('heading')
        ->and($data['heading'])->toBe('Hello')
        ->and($data)->not->toHaveKey('not_a_field');
});

it('reorders blocks in one write', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $first = blockOn($page, 'hero', 0);
    $second = blockOn($page, 'cta', 1);
    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->post(route('cms.pages.blocks.reorder', $page), ['ids' => [$second->id, $first->id]])
        ->assertRedirect();

    expect($second->fresh()?->order)->toBe(0)
        ->and($first->fresh()?->order)->toBe(1);
});

it('will not reorder a block belonging to another page', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $otherPage = Page::factory()->forCompany($company)->create();
    $foreign = blockOn($otherPage);
    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->post(route('cms.pages.blocks.reorder', $page), ['ids' => [$foreign->id]])
        ->assertSessionHasErrors('ids.0');
});

it('toggles and deletes a block', function (): void {
    $company = workspace();
    $page = Page::factory()->forCompany($company)->create();
    $block = blockOn($page);
    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)->post(route('cms.blocks.toggle', $block))->assertRedirect();
    expect($block->fresh()?->is_visible)->toBeFalse();

    actingAsMember($editor, $company)->delete(route('cms.blocks.destroy', $block))->assertRedirect();
    $this->assertDatabaseMissing('page_blocks', ['id' => $block->id]);
});

it('cannot touch a block belonging to another workspace', function (): void {
    $company = workspace();
    $other = workspace();
    $foreignPage = Page::factory()->forCompany($other)->create();
    $foreignBlock = blockOn($foreignPage);

    $editor = memberWith(['cms.pages.view', 'cms.pages.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->delete(route('cms.blocks.destroy', $foreignBlock))
        ->assertNotFound();
});
