<?php

declare(strict_types=1);

use App\Modules\Blog\Models\Post;
use App\Modules\SEO\DTOs\SeoMetaData;
use App\Modules\SEO\Models\SeoMeta;

use function Pest\Laravel\get;

it('redirects a guest away from the meta endpoint', function (): void {
    $company = workspace();
    $post = Post::factory()->forCompany($company)->create();

    get(route('seo.meta.show', ['type' => 'post', 'id' => $post->id]))->assertRedirect(route('login'));
});

it('forbids reading metadata without seo.view', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();
    $post = Post::factory()->forCompany($company)->create();

    actingAsMember($member, $company)
        ->getJson(route('seo.meta.show', ['type' => 'post', 'id' => $post->id]))
        ->assertForbidden();
});

it('returns an empty payload and a report for a post that has never been edited', function (): void {
    $company = workspace();
    $viewer = memberWith(['seo.view'], $company)->refresh();
    $post = Post::factory()->forCompany($company)->create();

    $response = actingAsMember($viewer, $company)
        ->getJson(route('seo.meta.show', ['type' => 'post', 'id' => $post->id]))
        ->assertOk()
        ->assertJsonPath('meta.title', null)
        ->assertJsonPath('meta.robots_index', true);

    expect($response->json('report.checks'))->not->toBeEmpty();
});

it('404s an unknown subject type', function (): void {
    $company = workspace();
    $viewer = memberWith(['seo.view'], $company)->refresh();

    actingAsMember($viewer, $company)
        ->getJson(route('seo.meta.show', ['type' => 'user', 'id' => 1]))
        ->assertNotFound();
});

it('404s a subject in another workspace', function (): void {
    $company = workspace();
    $other = workspace();

    $viewer = memberWith(['seo.view'], $company)->refresh();
    $foreign = Post::factory()->forCompany($other)->create();

    actingAsMember($viewer, $company)
        ->getJson(route('seo.meta.show', ['type' => 'post', 'id' => $foreign->id]))
        ->assertNotFound();
});

it('forbids writing metadata without seo.update', function (): void {
    $company = workspace();
    $viewer = memberWith(['seo.view'], $company)->refresh();
    $post = Post::factory()->forCompany($company)->create();

    actingAsMember($viewer, $company)
        ->patch(route('seo.meta.update'), ['type' => 'post', 'id' => $post->id, 'title' => 'Nope'])
        ->assertForbidden();
});

it('saves metadata against the subject and stamps the workspace', function (): void {
    $company = workspace();
    $editor = memberWith(['seo.view', 'seo.update'], $company)->refresh();
    $post = Post::factory()->forCompany($company)->create();

    actingAsMember($editor, $company)
        ->patch(route('seo.meta.update'), [
            'type' => 'post',
            'id' => $post->id,
            'title' => 'How we ship on Fridays',
            'description' => 'A short account of our release process and the tooling behind it.',
            'canonical_url' => 'https://example.com/blog/fridays',
            'robots_index' => true,
            'robots_follow' => false,
        ])
        ->assertRedirect();

    $meta = SeoMeta::query()->where('seoable_id', $post->id)->firstOrFail();

    expect($meta->title)->toBe('How we ship on Fridays')
        ->and($meta->company_id)->toBe($company->id)
        ->and($meta->robots_follow)->toBeFalse()
        ->and($meta->robots())->toBe('index, nofollow');
});

it('rejects a canonical URL that is not a URL', function (): void {
    $company = workspace();
    $editor = memberWith(['seo.view', 'seo.update'], $company)->refresh();
    $post = Post::factory()->forCompany($company)->create();

    actingAsMember($editor, $company)
        ->patch(route('seo.meta.update'), [
            'type' => 'post',
            'id' => $post->id,
            'canonical_url' => 'not-a-url',
        ])
        ->assertSessionHasErrors('canonical_url');
});

it('rejects an unknown subject type on write', function (): void {
    $company = workspace();
    $editor = memberWith(['seo.view', 'seo.update'], $company)->refresh();

    actingAsMember($editor, $company)
        ->patch(route('seo.meta.update'), ['type' => 'invoice', 'id' => 1])
        ->assertSessionHasErrors('type');
});

it('removes the override so the page falls back to the workspace defaults', function (): void {
    $company = workspace();
    $editor = memberWith(['seo.view', 'seo.update'], $company)->refresh();
    $post = Post::factory()->forCompany($company)->create();

    $post->saveSeo(SeoMetaData::fromArray(['title' => 'Temporary']));

    actingAsMember($editor, $company)
        ->delete(route('seo.meta.destroy', ['type' => 'post', 'id' => $post->id]))
        ->assertRedirect();

    $this->assertDatabaseMissing('seo_meta', ['seoable_id' => $post->id]);
});

it('takes a post metadata row with the post when it is force deleted', function (): void {
    $company = workspace();
    $post = Post::factory()->forCompany($company)->create();

    $post->saveSeo(SeoMetaData::fromArray(['title' => 'Doomed']));

    $post->forceDelete();

    $this->assertDatabaseMissing('seo_meta', ['seoable_id' => $post->id]);
});
