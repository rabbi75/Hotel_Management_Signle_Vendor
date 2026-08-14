<?php

declare(strict_types=1);

use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Tag;

use function Pest\Laravel\get;

it('redirects a guest away from the category screen', function (): void {
    workspace();

    get(route('blog.categories.index'))->assertRedirect(route('login'));
});

it('forbids a member with no blog permissions', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('blog.categories.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only categories in the active workspace', function (): void {
    $company = workspace();
    $other = workspace();

    Category::factory()->forCompany($company)->create(['name' => 'Ours']);
    Category::factory()->forCompany($other)->create(['name' => 'Theirs']);

    $viewer = memberWith(['blog.posts.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->get(route('blog.categories.index'), inertiaHeaders())
        ->assertOk();

    $names = collect($response->json('props.table.rows'))->pluck('name')->all();

    expect($names)->toContain('Ours')->and($names)->not->toContain('Theirs');
});

it('creates a category and derives its slug', function (): void {
    $company = workspace();
    $manager = memberWith(['blog.posts.view', 'blog.taxonomy.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('blog.categories.store'), ['name' => 'Product Updates'])
        ->assertRedirect();

    $this->assertDatabaseHas('blog_categories', [
        'company_id' => $company->id,
        'name' => 'Product Updates',
        'slug' => 'product-updates',
    ]);
});

it('lets two workspaces own the same category slug', function (): void {
    $company = workspace();
    $other = workspace();

    Category::factory()->forCompany($other)->create(['name' => 'News', 'slug' => 'news']);

    $manager = memberWith(['blog.posts.view', 'blog.taxonomy.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('blog.categories.store'), ['name' => 'News'])
        ->assertRedirect();

    $this->assertDatabaseHas('blog_categories', [
        'company_id' => $company->id,
        'slug' => 'news',
    ]);
});

it('rejects a category with no name', function (): void {
    $company = workspace();
    $manager = memberWith(['blog.posts.view', 'blog.taxonomy.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('blog.categories.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('forbids creating a category without blog.taxonomy.manage', function (): void {
    $company = workspace();
    $viewer = memberWith(['blog.posts.view'], $company)->refresh();

    actingAsMember($viewer, $company)
        ->post(route('blog.categories.store'), ['name' => 'Nope'])
        ->assertForbidden();
});

it('will not let a category be moved beneath its own descendant', function (): void {
    $company = workspace();
    $manager = memberWith(['blog.posts.view', 'blog.taxonomy.manage'], $company)->refresh();

    $parent = Category::factory()->forCompany($company)->create();
    $child = Category::factory()->forCompany($company)->create(['parent_id' => $parent->id]);

    actingAsMember($manager, $company)
        ->patch(route('blog.categories.update', $parent), [
            'name' => $parent->name,
            'parent_id' => $child->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

it('promotes children when a category is deleted', function (): void {
    $company = workspace();
    $manager = memberWith(['blog.posts.view', 'blog.taxonomy.manage'], $company)->refresh();

    $grandparent = Category::factory()->forCompany($company)->create();
    $parent = Category::factory()->forCompany($company)->create(['parent_id' => $grandparent->id]);
    $child = Category::factory()->forCompany($company)->create(['parent_id' => $parent->id]);

    actingAsMember($manager, $company)
        ->delete(route('blog.categories.destroy', $parent))
        ->assertRedirect();

    expect($child->fresh()?->parent_id)->toBe($grandparent->id);
});

it('creates and deletes a tag', function (): void {
    $company = workspace();
    $manager = memberWith(['blog.posts.view', 'blog.taxonomy.manage'], $company)->refresh();

    actingAsMember($manager, $company)
        ->post(route('blog.tags.store'), ['name' => 'Release'])
        ->assertRedirect();

    $tag = Tag::query()->where('company_id', $company->id)->firstOrFail();

    expect($tag->slug)->toBe('release');

    actingAsMember($manager, $company)
        ->delete(route('blog.tags.destroy', $tag))
        ->assertRedirect();

    $this->assertDatabaseMissing('blog_tags', ['id' => $tag->id]);
});

it('cannot delete a tag from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $manager = memberWith(['blog.posts.view', 'blog.taxonomy.manage'], $company)->refresh();
    $foreign = Tag::factory()->forCompany($other)->create();

    actingAsMember($manager, $company)
        ->delete(route('blog.tags.destroy', $foreign))
        ->assertNotFound();
});
