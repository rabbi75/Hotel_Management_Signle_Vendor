<?php

declare(strict_types=1);

use App\Modules\Blog\Models\Post;
use App\Modules\SEO\DTOs\SeoMetaData;
use App\Modules\SEO\Services\SeoManager;
use App\Modules\SEO\Services\StructuredData;
use App\Modules\SEO\Support\MetaTags;
use App\Support\Settings\SettingsRepository;
use App\Support\Tenancy\CurrentCompany;

use function Pest\Laravel\get;

/**
 * The head tags, and the one change this module makes to the shared root view:
 * a single `@stack('head')` in resources/views/app.blade.php.
 *
 * The end-to-end assertion deliberately uses `/`, whose page component is part
 * of the committed Vite manifest — a full HTML render of a page this task added
 * would fail on manifest lookup rather than on anything under test.
 */
beforeEach(function (): void {
    $this->company = workspace();
});

it('pushes the robots directive into the shared head stack', function (): void {
    app(CurrentCompany::class)->forget();

    $html = get('/')->assertOk()->getContent();

    expect($html)->toContain('<meta name="robots" content="noindex, nofollow">');
});

it('renders the resolved title, canonical, Open Graph and JSON-LD for a post', function (): void {
    app(SettingsRepository::class)->set('seo.robots_indexable', true, SettingsRepository::SCOPE_COMPANY, $this->company->id);

    $post = Post::factory()->forCompany($this->company)->published()->create([
        'slug' => 'meta-tags-post',
        'title' => 'Meta tags post',
        'excerpt' => 'A short summary of the post.',
    ]);

    $post->saveSeo(SeoMetaData::fromArray([
        'title' => 'A deliberately chosen SEO title',
        'description' => 'A deliberately chosen description.',
        'canonical_url' => 'https://example.com/blog/meta-tags-post',
    ]));

    $manager = app(SeoManager::class);
    $manager->share($manager->resolve($post->fresh() ?? $post, [StructuredData::article(headline: $post->title)]));

    $html = app(MetaTags::class)->render();

    expect($html)->toContain('<meta name="description" content="A deliberately chosen description.">')
        ->and($html)->toContain('<link rel="canonical" href="https://example.com/blog/meta-tags-post">')
        ->and($html)->toContain('<meta property="og:type" content="article">')
        ->and($html)->toContain('<meta name="robots" content="index, follow">')
        ->and($html)->toContain('application/ld+json')
        ->and($html)->toContain('"@type":"Article"');
});

it('forces noindex on a post even when the post itself asks to be indexed', function (): void {
    $post = Post::factory()->forCompany($this->company)->published()->create(['slug' => 'closed-site-post']);

    $post->saveSeo(SeoMetaData::fromArray(['robots_index' => true]));

    $manager = app(SeoManager::class);
    $manager->share($manager->resolve($post->fresh() ?? $post));

    expect(app(MetaTags::class)->render())->toContain('<meta name="robots" content="noindex, follow">');
});

it('escapes markup smuggled through a post title or excerpt', function (): void {
    app(SettingsRepository::class)->set('seo.robots_indexable', true, SettingsRepository::SCOPE_COMPANY, $this->company->id);

    $post = Post::factory()->forCompany($this->company)->published()->create([
        'slug' => 'xss-title',
        'title' => 'Break</script><script>alert(1)</script>',
        'excerpt' => 'Nothing to see" onload="alert(1)',
    ]);

    $manager = app(SeoManager::class);
    $manager->share($manager->resolve($post, [StructuredData::article(headline: $post->title)]));

    $html = app(MetaTags::class)->render();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('onload="alert(1)"')
        // Exactly one closing tag: the JSON-LD block's own.
        ->and(substr_count($html, '</script>'))->toBe(1);
});
