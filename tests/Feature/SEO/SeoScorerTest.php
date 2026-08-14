<?php

declare(strict_types=1);

use App\Modules\SEO\DTOs\SeoCheck;
use App\Modules\SEO\Enums\CheckStatus;
use App\Modules\SEO\Services\SeoScorer;
use App\Modules\SEO\Services\StructuredData;

/**
 * @param  list<SeoCheck>  $checks
 */
function check(array $checks, string $key): SeoCheck
{
    foreach ($checks as $candidate) {
        if ($candidate->key === $key) {
            return $candidate;
        }
    }

    throw new RuntimeException("No check named {$key}.");
}

beforeEach(function (): void {
    workspace();
    $this->scorer = app(SeoScorer::class);
});

it('says what is wrong rather than just scoring', function (): void {
    $report = $this->scorer->analyse([
        'title' => '',
        'description' => '',
        'canonical' => '',
        'slug' => '',
        'body_html' => '',
        'keyword' => '',
    ]);

    foreach ($report->checks as $singleCheck) {
        expect($singleCheck->message)->not->toBeEmpty()
            // Every message has to name an action, not a number.
            ->and(mb_strlen($singleCheck->message))->toBeGreaterThan(25);
    }

    // Not zero: a warning is honestly worth half credit, and an empty page has
    // several checks that cannot be evaluated rather than failed.
    expect($report->score())->toBeLessThan(30)
        ->and($report->counts(CheckStatus::Fail))->toBeGreaterThanOrEqual(4)
        ->and($report->failures())->not->toBeEmpty();
});

it('names the exact number of characters to remove from an overlong title', function (): void {
    $report = $this->scorer->analyse(['title' => str_repeat('a', 78)]);

    $title = check($report->checks, 'title');

    expect($title->status)->toBe(CheckStatus::Fail)
        ->and($title->message)->toContain('78')
        ->and($title->message)->toContain('18');
});

it('fails a page with more than one H1 and says how many to demote', function (): void {
    $report = $this->scorer->analyse(['body_html' => '<h1>One</h1><h1>Two</h1><h1>Three</h1>']);

    $headings = check($report->checks, 'headings');

    expect($headings->status)->toBe(CheckStatus::Fail)
        ->and($headings->message)->toContain('3')
        ->and($headings->message)->toContain('H2');
});

it('counts images missing alt text', function (): void {
    $report = $this->scorer->analyse([
        'body_html' => '<img src="a.png" alt="A chart"><img src="b.png"><img src="c.png" alt="">',
    ]);

    $images = check($report->checks, 'image_alt');

    expect($images->status)->toBe(CheckStatus::Fail)
        ->and($images->message)->toContain('2 of 3');
});

it('reports which of title, description and intro are missing the keyword', function (): void {
    $report = $this->scorer->analyse([
        'title' => 'Deploying on Fridays',
        'description' => 'Something entirely unrelated to the subject at hand, at length.',
        'body_html' => '<p>Nothing relevant here at all.</p>',
        'keyword' => 'friday deploys',
    ]);

    $keyword = check($report->checks, 'keyword');

    expect($keyword->status)->toBe(CheckStatus::Fail)
        ->and($keyword->message)->toContain('friday deploys');
});

it('passes a well-formed page', function (): void {
    $body = '<h1>Friday deploys</h1>'
        .'<p>Friday deploys are safe when the pipeline is. '.str_repeat('This is a short sentence about deploys. ', 80).'</p>'
        .'<h2>How</h2><p>See <a href="/blog/pipelines">our pipeline notes</a> and '
        .'<a href="https://example.org/study">the study</a>.</p>'
        .'<img src="/chart.png" alt="Deploy frequency by weekday">';

    $report = $this->scorer->analyse([
        'title' => 'Friday deploys: how we made them boring',
        'description' => 'Friday deploys used to be forbidden here. This is the tooling and the culture change that made shipping on a Friday unremarkable.',
        'canonical' => 'https://example.com/blog/friday-deploys',
        'slug' => 'friday-deploys',
        'body_html' => $body,
        'keyword' => 'Friday deploys',
    ]);

    expect($report->score())->toBeGreaterThan(85)
        ->and($report->failures())->toBe([]);
});

it('rejects a slug that is not lowercase and hyphenated', function (): void {
    $report = $this->scorer->analyse(['slug' => 'My_Post Title']);

    $slug = check($report->checks, 'slug');

    expect($slug->status)->toBe(CheckStatus::Fail)
        ->and($slug->message)->toContain('My_Post Title');
});

it('serialises a report for the client', function (): void {
    $payload = $this->scorer->analyse(['title' => 'A reasonable title for a blog post'])->toArray();

    expect($payload)->toHaveKeys(['score', 'passed', 'warnings', 'failed', 'checks'])
        ->and($payload['checks'][0])->toHaveKeys(['key', 'label', 'status', 'message', 'weight']);
});

it('escapes a script-closing sequence inside JSON-LD', function (): void {
    $script = StructuredData::script([
        StructuredData::article(headline: 'Break out</script><script>alert(1)</script>'),
    ]);

    expect($script)->toStartWith('<script type="application/ld+json">')
        ->and($script)->not->toContain('</script><script>')
        ->and(substr_count($script, '</script>'))->toBe(1);
});

it('builds the JSON-LD graphs it advertises', function (): void {
    expect(StructuredData::organization('Acme', 'https://acme.test')['@type'])->toBe('Organization')
        ->and(StructuredData::website('Acme', 'https://acme.test')['@type'])->toBe('WebSite')
        ->and(StructuredData::breadcrumbList([['name' => 'Blog', 'url' => 'https://acme.test/blog']])['@type'])->toBe('BreadcrumbList')
        ->and(StructuredData::article(headline: 'Hi')['@type'])->toBe('Article')
        ->and(StructuredData::faqPage([['question' => 'Why?', 'answer' => 'Because.']])['@type'])->toBe('FAQPage');
});
