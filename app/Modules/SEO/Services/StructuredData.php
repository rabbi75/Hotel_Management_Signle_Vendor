<?php

declare(strict_types=1);

namespace App\Modules\SEO\Services;

use DateTimeInterface;

/**
 * Builders for the JSON-LD graphs the kit emits.
 *
 * Every builder returns a plain array; serialisation happens once, in
 * {@see self::script()}, so escaping cannot be forgotten at a call site.
 */
class StructuredData
{
    /**
     * Flags that make a JSON-LD payload safe to inline in a <script> element.
     *
     * JSON_HEX_TAG is the load-bearing one: without it a value containing
     * `</script>` closes the block early and the remainder of the graph is
     * parsed as HTML.
     */
    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT;

    /**
     * @param  list<string>  $sameAs
     * @return array<string, mixed>
     */
    public static function organization(string $name, string $url, ?string $logo = null, array $sameAs = []): array
    {
        return self::compact([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'url' => $url,
            'logo' => $logo,
            'sameAs' => $sameAs === [] ? null : $sameAs,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function website(string $name, string $url, ?string $searchUrlTemplate = null): array
    {
        $graph = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $name,
            'url' => $url,
        ];

        if ($searchUrlTemplate !== null && $searchUrlTemplate !== '') {
            $graph['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchUrlTemplate,
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $graph;
    }

    /**
     * @param  list<array{name: string, url?: string|null}>  $crumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbList(array $crumbs): array
    {
        $items = [];

        foreach ($crumbs as $index => $crumb) {
            $items[] = self::compact([
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'] ?? null,
            ]);
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * @param  list<string>  $keywords
     * @return array<string, mixed>
     */
    public static function article(
        string $headline,
        ?string $description = null,
        ?string $url = null,
        ?string $image = null,
        ?string $authorName = null,
        ?string $publisherName = null,
        ?DateTimeInterface $publishedAt = null,
        ?DateTimeInterface $modifiedAt = null,
        array $keywords = [],
    ): array {
        return self::compact([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            // Google truncates the headline at 110 characters; sending more
            // just guarantees the tail is never seen.
            'headline' => mb_substr($headline, 0, 110),
            'description' => $description,
            'image' => $image,
            'author' => $authorName === null ? null : ['@type' => 'Person', 'name' => $authorName],
            'publisher' => $publisherName === null ? null : ['@type' => 'Organization', 'name' => $publisherName],
            'datePublished' => $publishedAt?->format(DateTimeInterface::ATOM),
            'dateModified' => ($modifiedAt ?? $publishedAt)?->format(DateTimeInterface::ATOM),
            'keywords' => $keywords === [] ? null : implode(', ', $keywords),
            'mainEntityOfPage' => $url === null ? null : ['@type' => 'WebPage', '@id' => $url],
        ]);
    }

    /**
     * @param  list<array{question: string, answer: string}>  $entries
     * @return array<string, mixed>
     */
    public static function faqPage(array $entries): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $entry): array => [
                '@type' => 'Question',
                'name' => $entry['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    // Plain text: schema.org allows HTML here, but accepting it
                    // would mean trusting author input inside a script block.
                    'text' => strip_tags($entry['answer']),
                ],
            ], $entries),
        ];
    }

    /**
     * Render one or more graphs as a complete <script> element.
     *
     * @param  list<array<string, mixed>>  $graphs
     */
    public static function script(array $graphs): string
    {
        $graphs = array_values(array_filter($graphs, static fn (array $graph): bool => $graph !== []));

        if ($graphs === []) {
            return '';
        }

        $payload = count($graphs) === 1 ? $graphs[0] : $graphs;

        return '<script type="application/ld+json">'.self::encode($payload).'</script>';
    }

    /**
     * @param  array<mixed>  $payload
     */
    public static function encode(array $payload): string
    {
        $json = json_encode($payload, self::ENCODE_FLAGS);

        return $json === false ? '{}' : $json;
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<string, mixed>
     */
    private static function compact(array $graph): array
    {
        return array_filter($graph, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
