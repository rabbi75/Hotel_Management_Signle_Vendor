<?php

declare(strict_types=1);

use App\Modules\Blog\Enums\CommentStatus;
use App\Modules\Blog\Models\Comment;
use App\Modules\Blog\Models\Post;

use function Pest\Laravel\get;

it('redirects a guest away from the moderation queue', function (): void {
    workspace();

    get(route('blog.comments.index'))->assertRedirect(route('login'));
});

it('forbids a member without blog.comments.moderate', function (): void {
    $company = workspace();
    $member = memberWith(['blog.posts.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('blog.comments.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists only comments in the active workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $mine = Comment::factory()->forPost(Post::factory()->forCompany($company)->create())->create(['body' => 'Ours']);
    Comment::factory()->forPost(Post::factory()->forCompany($other)->create())->create(['body' => 'Theirs']);

    $moderator = memberWith(['blog.comments.moderate'], $company)->refresh();

    $response = actingAsMember($moderator, $company)
        ->get(route('blog.comments.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'blog/comments/index');

    $ids = collect($response->json('props.table.rows'))->pluck('id')->all();

    expect($ids)->toContain($mine->id)->and($ids)->toHaveCount(1);
});

it('bulk approves comments', function (): void {
    $company = workspace();
    $moderator = memberWith(['blog.comments.moderate'], $company)->refresh();

    $post = Post::factory()->forCompany($company)->create();
    $comments = Comment::factory()->forPost($post)->count(3)->create();

    actingAsMember($moderator, $company)
        ->post(route('blog.comments.moderate'), [
            'action' => 'approve',
            'ids' => $comments->modelKeys(),
        ])
        ->assertRedirect();

    foreach ($comments as $comment) {
        expect($comment->fresh()?->status)->toBe(CommentStatus::Approved);
    }
});

it('rejects a moderation request with no ids', function (): void {
    $company = workspace();
    $moderator = memberWith(['blog.comments.moderate'], $company)->refresh();

    actingAsMember($moderator, $company)
        ->post(route('blog.comments.moderate'), ['action' => 'approve', 'ids' => []])
        ->assertSessionHasErrors('ids');
});

it('rejects an unknown moderation action', function (): void {
    $company = workspace();
    $moderator = memberWith(['blog.comments.moderate'], $company)->refresh();

    $comment = Comment::factory()->forPost(Post::factory()->forCompany($company)->create())->create();

    actingAsMember($moderator, $company)
        ->post(route('blog.comments.moderate'), ['action' => 'publish', 'ids' => [$comment->id]])
        ->assertSessionHasErrors('action');
});

it('refuses to moderate a comment belonging to another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $moderator = memberWith(['blog.comments.moderate'], $company)->refresh();
    $foreign = Comment::factory()->forPost(Post::factory()->forCompany($other)->create())->create();

    actingAsMember($moderator, $company)
        ->post(route('blog.comments.moderate'), [
            'action' => 'approve',
            'ids' => [$foreign->id],
        ])
        ->assertSessionHasErrors('ids.0');

    expect($foreign->fresh()?->status)->toBe(CommentStatus::Pending);
});

it('deletes comments in bulk', function (): void {
    $company = workspace();
    $moderator = memberWith(['blog.comments.moderate'], $company)->refresh();

    $comment = Comment::factory()->forPost(Post::factory()->forCompany($company)->create())->create();

    actingAsMember($moderator, $company)
        ->post(route('blog.comments.moderate'), ['action' => 'delete', 'ids' => [$comment->id]])
        ->assertRedirect();

    $this->assertDatabaseMissing('blog_comments', ['id' => $comment->id]);
});
