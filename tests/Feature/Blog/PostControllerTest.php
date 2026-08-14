<?php

declare(strict_types=1);

use App\Modules\Blog\Enums\BodyFormat;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\Tag;

use function Pest\Laravel\get;

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('blog.posts.index'))->assertRedirect(route('login'));
});

it('forbids a member without blog.posts.view from the index', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('blog.posts.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only posts in the active workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $mine = Post::factory()->forCompany($company)->create(['title' => 'Ours']);
    $theirs = Post::factory()->forCompany($other)->create(['title' => 'Theirs']);

    $viewer = memberWith(['blog.posts.view'], $company)->refresh();

    $response = actingAsMember($viewer, $company)
        ->get(route('blog.posts.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'blog/posts/index');

    $titles = collect($response->json('props.table.rows'))->pluck('title')->all();

    expect($titles)->toContain($mine->title)
        ->and($titles)->not->toContain($theirs->title);
});

it('creates a post and derives its slug, excerpt and reading time', function (): void {
    $company = workspace();
    $author = memberWith(['blog.posts.view', 'blog.posts.create'], $company)->refresh();

    $body = implode(' ', array_fill(0, 400, 'word'));

    actingAsMember($author, $company)
        ->post(route('blog.posts.store'), [
            'title' => 'Shipping On Fridays',
            'body' => $body,
            'body_format' => BodyFormat::Markdown->value,
            'status' => PostStatus::Draft->value,
        ])
        ->assertRedirect();

    $post = Post::query()->where('company_id', $company->id)->firstOrFail();

    expect($post->slug)->toBe('shipping-on-fridays')
        ->and($post->author_id)->toBe($author->id)
        // 400 words at the configured 200 wpm.
        ->and($post->reading_time)->toBe(2)
        ->and($post->excerpt)->not->toBeEmpty()
        ->and(mb_strlen((string) $post->excerpt))->toBeLessThanOrEqual(201);
});

it('rejects a post with no title', function (): void {
    $company = workspace();
    $author = memberWith(['blog.posts.view', 'blog.posts.create'], $company)->refresh();

    actingAsMember($author, $company)
        ->post(route('blog.posts.store'), [
            'title' => '',
            'body_format' => BodyFormat::Markdown->value,
            'status' => PostStatus::Draft->value,
        ])
        ->assertSessionHasErrors('title');
});

it('refuses a slug the public router already owns', function (): void {
    $company = workspace();
    $author = memberWith(['blog.posts.view', 'blog.posts.create'], $company)->refresh();

    actingAsMember($author, $company)
        ->post(route('blog.posts.store'), [
            'title' => 'Posts',
            'slug' => 'posts',
            'body_format' => BodyFormat::Markdown->value,
            'status' => PostStatus::Draft->value,
        ])
        ->assertSessionHasErrors('slug');
});

it('forbids creating a post without the create permission', function (): void {
    $company = workspace();
    $member = memberWith(['blog.posts.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->post(route('blog.posts.store'), [
            'title' => 'Nope',
            'body_format' => BodyFormat::Markdown->value,
            'status' => PostStatus::Draft->value,
        ])
        ->assertForbidden();
});

it('will not let a post reference a category from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $author = memberWith(['blog.posts.view', 'blog.posts.create'], $company)->refresh();
    $foreign = Category::factory()->forCompany($other)->create();

    actingAsMember($author, $company)
        ->post(route('blog.posts.store'), [
            'title' => 'Cross tenant',
            'body_format' => BodyFormat::Markdown->value,
            'status' => PostStatus::Draft->value,
            'category_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('category_id');
});

it('cannot edit a post belonging to another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $editor = memberWith(['blog.posts.view', 'blog.posts.update'], $company)->refresh();
    $foreign = Post::factory()->forCompany($other)->create();

    actingAsMember($editor, $company)
        ->get(route('blog.posts.edit', $foreign), inertiaHeaders())
        ->assertNotFound();
});

it('leaves the category alone when the request does not carry category_id', function (): void {
    $company = workspace();
    $editor = memberWith(['blog.posts.view', 'blog.posts.update'], $company)->refresh();

    $category = Category::factory()->forCompany($company)->create();
    $post = Post::factory()->forCompany($company)->create(['category_id' => $category->id]);

    actingAsMember($editor, $company)
        ->patch(route('blog.posts.update', $post), [
            'title' => 'Renamed',
            'body_format' => $post->body_format->value,
            'status' => $post->status->value,
        ])
        ->assertRedirect();

    expect($post->fresh()?->category_id)->toBe($category->id)
        ->and($post->fresh()?->title)->toBe('Renamed');
});

it('clears the category when category_id is explicitly submitted as null', function (): void {
    $company = workspace();
    $editor = memberWith(['blog.posts.view', 'blog.posts.update'], $company)->refresh();

    $category = Category::factory()->forCompany($company)->create();
    $post = Post::factory()->forCompany($company)->create(['category_id' => $category->id]);

    actingAsMember($editor, $company)
        ->patch(route('blog.posts.update', $post), [
            'title' => $post->title,
            'body_format' => $post->body_format->value,
            'status' => $post->status->value,
            'category_id' => null,
        ])
        ->assertRedirect();

    expect($post->fresh()?->category_id)->toBeNull();
});

it('does not rewrite the slug of a published post when its title changes', function (): void {
    $company = workspace();
    $editor = memberWith(['blog.posts.view', 'blog.posts.update'], $company)->refresh();

    $post = Post::factory()->forCompany($company)->published()->create([
        'title' => 'Original Title',
        'slug' => 'original-title',
    ]);

    actingAsMember($editor, $company)
        ->patch(route('blog.posts.update', $post), [
            'title' => 'A Completely Different Title',
            'body_format' => $post->body_format->value,
            'status' => $post->status->value,
        ])
        ->assertRedirect();

    expect($post->fresh()?->slug)->toBe('original-title');
});

it('syncs tags only when the request carries them', function (): void {
    $company = workspace();
    $editor = memberWith(['blog.posts.view', 'blog.posts.update'], $company)->refresh();

    $tag = Tag::factory()->forCompany($company)->create();
    $post = Post::factory()->forCompany($company)->create();
    $post->tags()->sync([$tag->id]);

    actingAsMember($editor, $company)
        ->patch(route('blog.posts.update', $post), [
            'title' => $post->title,
            'body_format' => $post->body_format->value,
            'status' => $post->status->value,
        ])
        ->assertRedirect();

    expect($post->fresh()?->tags()->count())->toBe(1);
});

it('duplicates a post as an unpublished draft with its own slug', function (): void {
    $company = workspace();
    $author = memberWith(['blog.posts.view', 'blog.posts.create', 'blog.posts.update'], $company)->refresh();

    $post = Post::factory()->forCompany($company)->published()->create([
        'title' => 'Launch Notes',
        'slug' => 'launch-notes',
    ]);

    actingAsMember($author, $company)
        ->post(route('blog.posts.duplicate', $post))
        ->assertRedirect();

    $copy = Post::query()->where('company_id', $company->id)->whereKeyNot($post->id)->firstOrFail();

    expect($copy->status)->toBe(PostStatus::Draft)
        ->and($copy->published_at)->toBeNull()
        ->and($copy->slug)->not->toBe($post->slug);
});

it('forbids deleting a post without the delete permission', function (): void {
    $company = workspace();
    $editor = memberWith(['blog.posts.view', 'blog.posts.update'], $company)->refresh();
    $post = Post::factory()->forCompany($company)->create();

    actingAsMember($editor, $company)
        ->delete(route('blog.posts.destroy', $post))
        ->assertForbidden();
});

it('returns an actionable SEO report with the editor', function (): void {
    $company = workspace();
    $editor = memberWith(['blog.posts.view', 'blog.posts.update'], $company)->refresh();
    $post = Post::factory()->forCompany($company)->create(['body_html' => '<p>Short.</p>']);

    $response = actingAsMember($editor, $company)
        ->get(route('blog.posts.edit', $post), inertiaHeaders())
        ->assertOk();

    $checks = collect($response->json('props.report.checks'));

    expect($checks)->not->toBeEmpty()
        ->and($checks->pluck('key')->all())->toContain('title', 'description', 'slug')
        ->and($checks->every(fn (array $check): bool => mb_strlen($check['message']) > 20))->toBeTrue();
});
