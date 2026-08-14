<?php

declare(strict_types=1);

use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Models\Menu;
use App\Modules\CMS\Models\MenuItem;
use App\Modules\CMS\Models\Page;

use function Pest\Laravel\get;

function menuItemOn(Menu $menu, string $label, ?int $parentId = null, int $order = 0): MenuItem
{
    $item = new MenuItem([
        'menu_id' => $menu->id,
        'parent_id' => $parentId,
        'page_id' => null,
        'label' => $label,
        'url' => '/'.str($label)->slug(),
        'target' => '_self',
        'icon' => null,
        'permission' => null,
        'order' => $order,
    ]);

    // `company_id` is guarded; stamped explicitly so a fixture can be built for
    // a workspace other than the active one.
    $item->company_id = $menu->company_id;
    $item->save();

    return $item;
}

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('cms.menus.index'))->assertRedirect(route('login'));
});

it('forbids a member without cms.menus.manage', function (): void {
    $company = workspace();
    $member = memberWith(['cms.pages.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('cms.menus.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only menus in the active workspace, as a tree', function (): void {
    $company = workspace();
    $other = workspace();

    $menu = Menu::factory()->forCompany($company)->create(['name' => 'Main navigation']);
    $parent = menuItemOn($menu, 'Products');
    menuItemOn($menu, 'Widgets', $parent->id, 1);

    Menu::factory()->forCompany($other)->create(['name' => 'Their navigation']);

    $manager = memberWith(['cms.menus.manage'], $company)->refresh();

    $response = actingAsMember($manager, $company)
        ->get(route('cms.menus.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'cms/menus/index');

    $names = collect($response->json('props.menus'))->pluck('name')->all();

    expect($names)->toContain('Main navigation')
        ->and($names)->not->toContain('Their navigation')
        ->and($response->json('props.menus.0.items.0.children.0.label'))->toBe('Widgets');
});

it('creates a menu', function (): void {
    $company = workspace();
    $manager = memberWith(['cms.menus.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('cms.menus.store'), ['name' => 'Footer links', 'location' => MenuLocation::Footer->value])
        ->assertRedirect();

    $this->assertDatabaseHas('menus', [
        'company_id' => $company->id,
        'name' => 'Footer links',
        'location' => MenuLocation::Footer->value,
    ]);
});

it('rejects a second menu in the same location', function (): void {
    $company = workspace();
    Menu::factory()->forCompany($company)->location(MenuLocation::Header)->create();
    $manager = memberWith(['cms.menus.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('cms.menus.store'), ['name' => 'Another header', 'location' => MenuLocation::Header->value])
        ->assertSessionHasErrors('location');
});

it('adds an item that points at a page', function (): void {
    $company = workspace();
    $menu = Menu::factory()->forCompany($company)->create();
    $page = Page::factory()->forCompany($company)->published()->create(['slug' => 'about']);
    $manager = memberWith(['cms.menus.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('cms.menus.items.store', $menu), ['label' => 'About', 'page_id' => $page->id])
        ->assertRedirect();

    $this->assertDatabaseHas('menu_items', ['menu_id' => $menu->id, 'label' => 'About', 'page_id' => $page->id]);
});

it('rejects an item that points at neither a page nor a url', function (): void {
    $company = workspace();
    $menu = Menu::factory()->forCompany($company)->create();
    $manager = memberWith(['cms.menus.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('cms.menus.items.store', $menu), ['label' => 'Nowhere'])
        ->assertSessionHasErrors(['page_id', 'url']);
});

it('persists a nested drag-and-drop reorder', function (): void {
    $company = workspace();
    $menu = Menu::factory()->forCompany($company)->create();
    $first = menuItemOn($menu, 'One', null, 0);
    $second = menuItemOn($menu, 'Two', null, 1);
    $manager = memberWith(['cms.menus.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('cms.menus.items.reorder', $menu), [
            'items' => [
                ['id' => $second->id, 'parent_id' => null, 'order' => 0],
                ['id' => $first->id, 'parent_id' => $second->id, 'order' => 0],
            ],
        ])
        ->assertRedirect();

    expect($first->fresh()?->parent_id)->toBe($second->id)
        ->and($second->fresh()?->order)->toBe(0);
});

it('cannot reach a menu in another workspace', function (): void {
    $company = workspace();
    $other = workspace();
    $foreign = Menu::factory()->forCompany($other)->create();

    $manager = memberWith(['cms.menus.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->delete(route('cms.menus.destroy', $foreign))
        ->assertNotFound();
});
