<?php

declare(strict_types=1);

use App\Modules\Blog\Enums\BodyFormat;
use App\Modules\Blog\Enums\CommentStatus;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Comment;
use App\Modules\Blog\Models\Post;
use App\Modules\Blog\Models\Tag;
use App\Modules\Blog\Services\PostRenderer;
use App\Support\Tenancy\CurrentCompany;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * The public blog resolves its workspace from the host or, failing that, the
 * oldest one. These tests create the workspace under test first so that
 * fallback lands on it, and a second workspace afterwards to stand in for
 * "another customer".
 */
beforeEach(function (): void {
    $this->company = workspace();
    app(CurrentCompany::class)->forget();
});

it('lists published posts to an anonymous reader', function (): void {
    $live = Post::factory()->forCompany($this->company)->published()->create(['title' => 'Live one']);

    $response = get(route('blog.public.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'blog/public/index');

    expect(collect($response->json('props.posts.data'))->pluck('title')->all())->toContain($live->title);
});

it('404s a draft', function (): void {
    $draft = Post::factory()->forCompany($this->company)->create(['slug' => 'secret-draft']);

    expect($draft->status)->toBe(PostStatus::Draft);

    get(route('blog.public.show', 'secret-draft'), inertiaHeaders())->assertNotFound();
});

it('hides a scheduled post until the command has run', function (): void {
    $post = Post::factory()->forCompany($this->company)->scheduled(now()->addHour())->create([
        'slug' => 'tomorrows-news',
    ]);

    get(route('blog.public.show', 'tomorrows-news'), inertiaHeaders())->assertNotFound();

    // Still not due, so the command must leave it alone.
    $this->artisan('blog:publish-scheduled')->assertSuccessful();
    get(route('blog.public.show', 'tomorrows-news'), inertiaHeaders())->assertNotFound();

    $post->forceFill(['published_at' => now()->subMinute()])->save();

    $this->artisan('blog:publish-scheduled')->assertSuccessful();

    expect($post->fresh()?->status)->toBe(PostStatus::Published);

    get(route('blog.public.show', 'tomorrows-news'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'blog/public/show');
});

it('does not serve a post belonging to another workspace', function (): void {
    $other = workspace();
    app(CurrentCompany::class)->forget();

    Post::factory()->forCompany($other)->published()->create(['slug' => 'their-post']);

    get(route('blog.public.show', 'their-post'), inertiaHeaders())->assertNotFound();
});

it('strips script tags out of markdown instead of rendering them', function (): void {
    $renderer = app(PostRenderer::class);

    $html = $renderer->render("Hello\n\n<script>alert('xss')</script>\n\n[click](javascript:alert(1))", BodyFormat::Markdown);

    expect($html)->not->toContain('<script')
        ->and($html)->not->toContain('alert(')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->toContain('Hello');
});

it('strips script tags out of rich text instead of rendering them', function (): void {
    $renderer = app(PostRenderer::class);

    $html = $renderer->render('<p onclick="steal()">Hi</p><script>alert(1)</script><img src="x" onerror="alert(1)">', BodyFormat::Html);

    expect($html)->not->toContain('<script')
        ->and($html)->not->toContain('onclick')
        ->and($html)->not->toContain('onerror')
        ->and($html)->toContain('Hi');
});

it('serves a category archive including its subcategories', function (): void {
    $parent = Category::factory()->forCompany($this->company)->create(['name' => 'Engineering', 'slug' => 'engineering']);
    $child = Category::factory()->forCompany($this->company)->create(['parent_id' => $parent->id]);

    $inChild = Post::factory()->forCompany($this->company)->published()->create(['category_id' => $child->id]);

    $response = get(route('blog.public.category', 'engineering'), inertiaHeaders())->assertOk();

    expect(collect($response->json('props.posts.data'))->pluck('id')->all())->toContain($inChild->id);
});

it('serves a tag archive', function (): void {
    $tag = Tag::factory()->forCompany($this->company)->create(['slug' => 'release']);
    $post = Post::factory()->forCompany($this->company)->published()->create();
    $post->tags()->sync([$tag->id]);

    $response = get(route('blog.public.tag', 'release'), inertiaHeaders())->assertOk();

    expect(collect($response->json('props.posts.data'))->pluck('id')->all())->toContain($post->id);
});

it('serves an RSS feed of published posts only', function (): void {
    Post::factory()->forCompany($this->company)->published()->create(['title' => 'Published item']);
    Post::factory()->forCompany($this->company)->create(['title' => 'Draft item']);

    $response = get(route('blog.public.feed'))->assertOk();

    $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    expect($response->getContent())->toContain('Published item')
        ->and($response->getContent())->not->toContain('Draft item');
});

it('rejects a guest comment when commenting is disabled', function (): void {
    config()->set('saas.blog.comments_enabled', false);

    Post::factory()->forCompany($this->company)->published()->create(['slug' => 'open-post']);

    post(route('blog.public.comment', 'open-post'), [
        'body' => 'Nice post',
        'guest_name' => 'Reader',
        'guest_email' => 'reader@example.com',
    ])->assertForbidden();
});

it('holds a guest comment for moderation and never trusts a submitted status', function (): void {
    config()->set('saas.blog.comments_enabled', true);
    config()->set('saas.blog.comments_require_approval', true);

    $post = Post::factory()->forCompany($this->company)->published()->create(['slug' => 'open-post']);

    post(route('blog.public.comment', 'open-post'), [
        'body' => 'Nice post',
        'guest_name' => 'Reader',
        'guest_email' => 'reader@example.com',
        'status' => CommentStatus::Approved->value,
    ])->assertRedirect();

    $comment = Comment::query()->withoutGlobalScopes()->where('post_id', $post->id)->firstOrFail();

    expect($comment->status)->toBe(CommentStatus::Pending)
        ->and($comment->company_id)->toBe($this->company->id);
});

it('validates a guest comment', function (): void {
    config()->set('saas.blog.comments_enabled', true);

    Post::factory()->forCompany($this->company)->published()->create(['slug' => 'open-post']);

    post(route('blog.public.comment', 'open-post'), [
        'body' => '',
        'guest_name' => '',
        'guest_email' => 'not-an-email',
    ])->assertSessionHasErrors(['body', 'guest_name', 'guest_email']);
});

it('rate limits anonymous comment submissions', function (): void {
    config()->set('saas.blog.comments_enabled', true);

    Post::factory()->forCompany($this->company)->published()->create(['slug' => 'open-post']);

    $payload = [
        'body' => 'Nice post',
        'guest_name' => 'Reader',
        'guest_email' => 'reader@example.com',
    ];

    for ($attempt = 0; $attempt < 5; $attempt++) {
        post(route('blog.public.comment', 'open-post'), $payload)->assertRedirect();
    }

    post(route('blog.public.comment', 'open-post'), $payload)->assertStatus(429);
});
