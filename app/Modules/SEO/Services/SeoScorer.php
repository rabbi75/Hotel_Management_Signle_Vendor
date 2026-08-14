<?php

declare(strict_types=1);

namespace App\Modules\SEO\Services;

use App\Modules\SEO\DTOs\SeoCheck;
use App\Modules\SEO\DTOs\SeoReport;

/**
 * An honest on-page audit.
 *
 * Every check answers with a specific instruction — "the title is 78
 * characters; trim 18 so Google stops cutting it off" — because a bare number
 * tells an author nothing they can act on. Checks that cannot be evaluated
 * (no focus keyword given, no body yet) say so rather than silently passing.
 */
class SeoScorer
{
    /** Shortest body, in words, that is worth analysing as prose. */
    private const MIN_WORDS = 300;

    /** Average words per sentence above which prose reads as heavy. */
    private const HEAVY_SENTENCE = 25;

    public function __construct(protected SeoManager $manager) {}

    /**
     * @param  array{title?: string|null, description?: string|null, canonical?: string|null, slug?: string|null, body_html?: string|null, keyword?: string|null}  $page
     */
    public function analyse(array $page): SeoReport
    {
        $title = trim((string) ($page['title'] ?? ''));
        $description = trim((string) ($page['description'] ?? ''));
        $canonical = trim((string) ($page['canonical'] ?? ''));
        $slug = trim((string) ($page['slug'] ?? ''));
        $html = (string) ($page['body_html'] ?? '');
        $keyword = trim((string) ($page['keyword'] ?? ''));

        $text = $this->plainText($html);

        return new SeoReport([
            $this->checkTitle($title),
            $this->checkDescription($description),
            $this->checkCanonical($canonical),
            $this->checkHeadings($html),
            $this->checkImageAlts($html),
            $this->checkLinks($html),
            $this->checkKeyword($keyword, $title, $description, $text),
            $this->checkLength($text),
            $this->checkReadability($text),
            $this->checkSlug($slug),
        ]);
    }

    protected function checkTitle(string $title): SeoCheck
    {
        $max = $this->manager->titleMax();
        $length = mb_strlen($title);

        if ($length === 0) {
            return SeoCheck::fail('title', 'Title', 'There is no SEO title. Write one of about '.$max.' characters — it is the headline searchers actually click.', 3);
        }

        if ($length > $max) {
            return SeoCheck::fail('title', 'Title', "The title is {$length} characters. Remove ".($length - $max)." so it fits the {$max}-character limit instead of being truncated with an ellipsis.", 3);
        }

        if ($length < 30) {
            return SeoCheck::warn('title', 'Title', "The title is only {$length} characters. There is room for roughly ".($max - $length).' more — use it to say who the page is for.', 3);
        }

        return SeoCheck::pass('title', 'Title', "Good length: {$length} of {$max} characters.", 3);
    }

    protected function checkDescription(string $description): SeoCheck
    {
        $max = $this->manager->descriptionMax();
        $length = mb_strlen($description);

        if ($length === 0) {
            return SeoCheck::fail('description', 'Meta description', "There is no meta description, so search engines will quote an arbitrary sentence from the page. Write {$max} characters that answer \"why should I open this?\".", 3);
        }

        if ($length > $max) {
            return SeoCheck::fail('description', 'Meta description', "The description is {$length} characters and will be cut at {$max}. Trim ".($length - $max).' characters, keeping the call to action at the front.', 3);
        }

        if ($length < 70) {
            return SeoCheck::warn('description', 'Meta description', "At {$length} characters the description wastes most of the snippet. Aim for 120–{$max}.", 3);
        }

        return SeoCheck::pass('description', 'Meta description', "Good length: {$length} of {$max} characters.", 3);
    }

    protected function checkCanonical(string $canonical): SeoCheck
    {
        if ($canonical === '') {
            return SeoCheck::warn('canonical', 'Canonical URL', 'No canonical URL is set. Add one so that query strings and tracking parameters do not split this page into several competing duplicates.', 2);
        }

        if (filter_var($canonical, FILTER_VALIDATE_URL) === false) {
            return SeoCheck::fail('canonical', 'Canonical URL', "\"{$canonical}\" is not an absolute URL. Canonical links must include the scheme and host, e.g. https://example.com/blog/post.", 2);
        }

        if (! str_starts_with($canonical, 'https://')) {
            return SeoCheck::warn('canonical', 'Canonical URL', 'The canonical URL is not https. Point it at the secure version or engines may keep indexing the insecure one.', 2);
        }

        return SeoCheck::pass('canonical', 'Canonical URL', 'A single absolute https canonical is declared.', 2);
    }

    protected function checkHeadings(string $html): SeoCheck
    {
        $h1 = preg_match_all('/<h1\b[^>]*>/i', $html);
        $h2 = preg_match_all('/<h2\b[^>]*>/i', $html);

        if ($h1 === false || $h2 === false) {
            return SeoCheck::warn('headings', 'Headings', 'The body could not be parsed for headings.', 2);
        }

        if ($h1 === 0) {
            return SeoCheck::warn('headings', 'Headings', 'The body contains no H1. That is correct when the page template already renders the title as the H1 — confirm it does, because otherwise the page has no top-level heading at all.', 2);
        }

        if ($h1 > 1) {
            return SeoCheck::fail('headings', 'Headings', "The body contains {$h1} H1 headings. Keep exactly one and demote the other ".($h1 - 1).' to H2, so the document outline has a single subject.', 2);
        }

        if ($h2 === 0) {
            return SeoCheck::warn('headings', 'Headings', 'There is one H1 but no H2s. Break the body into sections so readers can scan it.', 2);
        }

        return SeoCheck::pass('headings', 'Headings', "One H1 and {$h2} H2 sections — a clean outline.", 2);
    }

    protected function checkImageAlts(string $html): SeoCheck
    {
        $total = preg_match_all('/<img\b[^>]*>/i', $html, $matches);

        if ($total === false || $total === 0) {
            return SeoCheck::warn('image_alt', 'Image alt text', 'The page has no images. A diagram or screenshot both helps readers and gives image search something to index.', 2);
        }

        $missing = 0;

        foreach ($matches[0] as $tag) {
            if (preg_match('/\balt\s*=\s*("[^"]*[^"\s][^"]*"|\'[^\']*[^\'\s][^\']*\')/i', $tag) !== 1) {
                $missing++;
            }
        }

        if ($missing > 0) {
            return SeoCheck::fail('image_alt', 'Image alt text', "{$missing} of {$total} images have no alt text. Describe what each one shows — screen readers announce nothing otherwise, and the images cannot be indexed.", 2);
        }

        return SeoCheck::pass('image_alt', 'Image alt text', "All {$total} images carry alt text.", 2);
    }

    protected function checkLinks(string $html): SeoCheck
    {
        $count = preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $matches);

        if ($count === false || $count === 0) {
            return SeoCheck::fail('links', 'Links', 'The page links nowhere. Add at least one link to a related page of your own so this post is not a dead end in the site graph.', 2);
        }

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $internal = 0;
        $external = 0;

        foreach ($matches[1] as $href) {
            $linkHost = parse_url($href, PHP_URL_HOST);

            if ($linkHost === null || $linkHost === false || $linkHost === $host) {
                $internal++;

                continue;
            }

            $external++;
        }

        if ($internal === 0) {
            return SeoCheck::warn('links', 'Links', "There are {$external} outbound links but none to your own pages. Link to a related post so readers — and crawlers — have somewhere to go next.", 2);
        }

        if ($external === 0) {
            return SeoCheck::warn('links', 'Links', "There are {$internal} internal links and no outbound ones. Citing a source or two is a credibility signal, not a leak.", 2);
        }

        return SeoCheck::pass('links', 'Links', "{$internal} internal and {$external} outbound links.", 2);
    }

    protected function checkKeyword(string $keyword, string $title, string $description, string $text): SeoCheck
    {
        if ($keyword === '') {
            return SeoCheck::warn('keyword', 'Focus keyword', 'No focus keyword is set, so this page cannot be checked for topical focus. Add the phrase you want to rank for in the SEO panel.', 3);
        }

        $intro = $this->firstParagraph($text);
        $missing = [];

        if (! $this->contains($title, $keyword)) {
            $missing[] = 'the title';
        }

        if (! $this->contains($description, $keyword)) {
            $missing[] = 'the meta description';
        }

        if (! $this->contains($intro, $keyword)) {
            $missing[] = 'the first paragraph';
        }

        if ($missing === []) {
            return SeoCheck::pass('keyword', 'Focus keyword', "\"{$keyword}\" appears in the title, the description and the opening paragraph.", 3);
        }

        if (count($missing) === 3) {
            return SeoCheck::fail('keyword', 'Focus keyword', "\"{$keyword}\" appears in none of the title, the meta description or the first paragraph. Either work it in naturally or pick a keyword the page is actually about.", 3);
        }

        return SeoCheck::warn('keyword', 'Focus keyword', "\"{$keyword}\" is missing from ".$this->conjoin($missing).'. Add it where it reads naturally.', 3);
    }

    protected function checkLength(string $text): SeoCheck
    {
        $words = $this->wordCount($text);

        if ($words === 0) {
            return SeoCheck::fail('length', 'Content length', 'The page has no body text at all. Nothing else on this list can help until there is something to read.', 2);
        }

        if ($words < self::MIN_WORDS) {
            return SeoCheck::warn('length', 'Content length', "The body is {$words} words. Below ".self::MIN_WORDS.' words a page rarely covers a topic completely enough to outrank one that does — add roughly '.(self::MIN_WORDS - $words).' more, or accept that this is a short note.', 2);
        }

        return SeoCheck::pass('length', 'Content length', "{$words} words — enough to cover a topic properly.", 2);
    }

    protected function checkReadability(string $text): SeoCheck
    {
        $words = $this->wordCount($text);

        if ($words < 40) {
            return SeoCheck::warn('readability', 'Readability', 'There is too little text to judge readability yet.', 1);
        }

        $sentences = max(1, preg_match_all('/[.!?]+(\s|$)/u', $text));
        $average = (int) round($words / $sentences);

        if ($average > self::HEAVY_SENTENCE) {
            return SeoCheck::fail('readability', 'Readability', "Sentences average {$average} words. Split the longest ones; anything past ".self::HEAVY_SENTENCE.' words is hard to follow on a phone.', 1);
        }

        if ($average > 20) {
            return SeoCheck::warn('readability', 'Readability', "Sentences average {$average} words, which is on the heavy side. Aim for under 20.", 1);
        }

        return SeoCheck::pass('readability', 'Readability', "Sentences average {$average} words — comfortable to read.", 1);
    }

    protected function checkSlug(string $slug): SeoCheck
    {
        if ($slug === '') {
            return SeoCheck::fail('slug', 'URL slug', 'The page has no slug, so its URL carries no meaning. Derive one from the title.', 2);
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return SeoCheck::fail('slug', 'URL slug', "\"{$slug}\" contains characters outside a-z, 0-9 and hyphens. Mixed case and underscores make URLs that are easy to mistype and awkward to share.", 2);
        }

        $words = substr_count($slug, '-') + 1;

        if (mb_strlen($slug) > 75 || $words > 8) {
            return SeoCheck::warn('slug', 'URL slug', "The slug is {$words} words long. Cut it to three to five meaningful ones — the rest is noise in a search result.", 2);
        }

        if (mb_strlen($slug) < 3) {
            return SeoCheck::warn('slug', 'URL slug', "\"{$slug}\" is too short to describe the page. Use a few words from the title.", 2);
        }

        return SeoCheck::pass('slug', 'URL slug', "\"{$slug}\" is short, lowercase and hyphenated.", 2);
    }

    /**
     * @param  list<string>  $items
     */
    protected function conjoin(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }

    protected function contains(string $haystack, string $needle): bool
    {
        return $haystack !== '' && mb_stripos($haystack, $needle) !== false;
    }

    protected function firstParagraph(string $text): string
    {
        $parts = preg_split('/\n{2,}/', trim($text), 2);

        return is_array($parts) && $parts !== [] ? trim($parts[0]) : trim($text);
    }

    protected function wordCount(string $text): int
    {
        $matched = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $text);

        return $matched === false ? 0 : $matched;
    }

    /**
     * Block elements become paragraph breaks so "the first paragraph" means the
     * first paragraph, not the first 200 characters of an unbroken blob.
     */
    protected function plainText(string $html): string
    {
        $withBreaks = preg_replace('#</(p|div|li|h[1-6]|blockquote|pre)>#i', "\n\n", $html);

        return trim(html_entity_decode(strip_tags($withBreaks ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
