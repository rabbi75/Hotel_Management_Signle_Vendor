<?php

declare(strict_types=1);

use App\Modules\Blog\Models\Post;
use App\Modules\SEO\Services\SitemapGenerator;
use App\Support\Settings\SettingsRepository;
use App\Support\Tenancy\CurrentCompany;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function (): void {
    $this->company = workspace();

    $this->sitemapPath = app(SitemapGenerator::class)->path();

    if (is_file($this->sitemapPath)) {
        unlink($this->sitemapPath);
    }
});

afterEach(function (): void {
    if (is_file($this->sitemapPath)) {
        unlink($this->sitemapPath);
    }
});

it('disallows everything when the deployment is not indexable', function (): void {
    app(CurrentCompany::class)->forget();

    $response = get('/robots.txt')->assertOk();

    expect($response->getContent())->toContain('User-agent: *')
        ->and($response->getContent())->toContain('Disallow: /')
        ->and($response->getContent())->not->toContain('Sitemap:');
});

it('opens the site and advertises the sitemap once indexing is enabled', function (): void {
    app(SettingsRepository::class)->set('seo.robots_indexable', true, SettingsRepository::SCOPE_COMPANY, $this->company->id);

    app(CurrentCompany::class)->forget();

    $response = get('/robots.txt')->assertOk();

    expect($response->getContent())->toContain('Allow: /')
        ->and($response->getContent())->toContain('Sitemap:')
        ->and($response->getContent())->toContain('Disallow: /dashboard');
});

it('404s the sitemap until one has been generated', function (): void {
    app(CurrentCompany::class)->forget();

    get('/sitemap.xml')->assertNotFound();
});

it('excludes drafts and other workspaces from the sitemap', function (): void {
    $other = workspace();
    app(CurrentCompany::class)->set($this->company);

    $live = Post::factory()->forCompany($this->company)->published()->create(['slug' => 'ours-live']);
    Post::factory()->forCompany($this->company)->create(['slug' => 'ours-draft']);
    Post::factory()->forCompany($this->company)->scheduled()->create(['slug' => 'ours-scheduled']);
    Post::factory()->forCompany($other)->published()->create(['slug' => 'theirs-live']);

    app(SitemapGenerator::class)->generate($this->company);

    $xml = (string) file_get_contents($this->sitemapPath);

    expect($xml)->toContain($live->slug)
        ->and($xml)->not->toContain('ours-draft')
        ->and($xml)->not->toContain('ours-scheduled')
        ->and($xml)->not->toContain('theirs-live');
});

it('forbids regenerating the sitemap without seo.sitemap.generate', function (): void {
    $viewer = memberWith(['seo.view'], $this->company)->refresh();

    actingAsMember($viewer, $this->company)
        ->post(route('seo.sitemap.generate'))
        ->assertForbidden();
});

it('redirects a guest away from regenerating the sitemap', function (): void {
    post(route('seo.sitemap.generate'))->assertRedirect(route('login'));
});

it('regenerates the sitemap and records a security event', function (): void {
    $operator = memberWith(['seo.view', 'seo.sitemap.generate'], $this->company)->refresh();

    Post::factory()->forCompany($this->company)->published()->create(['slug' => 'generated-post']);

    actingAsMember($operator, $this->company)
        ->post(route('seo.sitemap.generate'))
        ->assertRedirect();

    expect(is_file($this->sitemapPath))->toBeTrue()
        ->and((string) file_get_contents($this->sitemapPath))->toContain('generated-post');

    $this->assertDatabaseHas('security_logs', [
        'company_id' => $this->company->id,
        'event' => 'sitemap_generated',
    ]);
});

it('generates from the console for a named workspace', function (): void {
    Post::factory()->forCompany($this->company)->published()->create(['slug' => 'console-post']);

    $this->artisan('seo:sitemap', ['--company' => (string) $this->company->id])->assertSuccessful();

    expect((string) file_get_contents($this->sitemapPath))->toContain('console-post');
});
