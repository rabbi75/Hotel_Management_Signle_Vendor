<?php

declare(strict_types=1);

use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Models\Post;

use function Pest\Laravel\post;

it('redirects a guest away from publishing', function (): void {
    $company = workspace();
    $target = Post::factory()->forCompany($company)->create();

    post(route('blog.posts.publish', $target))->assertRedirect(route('login'));
});

it('forbids publishing without blog.posts.publish', function (): void {
    $company = workspace();
    $editor = memberWith(['blog.posts.view', 'blog.posts.update'], $company)->refresh();
    $target = Post::factory()->forCompany($company)->create();

    actingAsMember($editor, $company)
        ->post(route('blog.posts.publish', $target))
        ->assertForbidden();
});

it('publishes a draft immediately when no date is given', function (): void {
    $company = workspace();
    $publisher = memberWith(['blog.posts.view', 'blog.posts.publish'], $company)->refresh();
    $target = Post::factory()->forCompany($company)->create();

    actingAsMember($publisher, $company)
        ->post(route('blog.posts.publish', $target))
        ->assertRedirect();

    $target->refresh();

    expect($target->status)->toBe(PostStatus::Published)
        ->and($target->published_at)->not->toBeNull()
        ->and($target->isPublished())->toBeTrue();
});

it('treats a future date as a schedule whichever endpoint is used', function (): void {
    $company = workspace();
    $publisher = memberWith(['blog.posts.view', 'blog.posts.publish'], $company)->refresh();
    $target = Post::factory()->forCompany($company)->create();

    actingAsMember($publisher, $company)
        ->post(route('blog.posts.publish', $target), ['published_at' => now()->addDays(2)->toIso8601String()])
        ->assertRedirect();

    expect($target->fresh()?->status)->toBe(PostStatus::Scheduled);
});

it('rejects a schedule in the past', function (): void {
    $company = workspace();
    $publisher = memberWith(['blog.posts.view', 'blog.posts.publish'], $company)->refresh();
    $target = Post::factory()->forCompany($company)->create();

    actingAsMember($publisher, $company)
        ->post(route('blog.posts.schedule', $target), ['published_at' => now()->subDay()->toIso8601String()])
        ->assertSessionHasErrors('published_at');
});

it('unpublishes without discarding the original publication date', function (): void {
    $company = workspace();
    $publisher = memberWith(['blog.posts.view', 'blog.posts.publish'], $company)->refresh();
    $target = Post::factory()->forCompany($company)->published()->create();

    $originalDate = $target->published_at;

    actingAsMember($publisher, $company)
        ->delete(route('blog.posts.unpublish', $target))
        ->assertRedirect();

    $target->refresh();

    expect($target->status)->toBe(PostStatus::Draft)
        ->and($target->published_at?->toIso8601String())->toBe($originalDate?->toIso8601String());
});

it('cannot publish a post from another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $publisher = memberWith(['blog.posts.view', 'blog.posts.publish'], $company)->refresh();
    $foreign = Post::factory()->forCompany($other)->create();

    actingAsMember($publisher, $company)
        ->post(route('blog.posts.publish', $foreign))
        ->assertNotFound();
});
