<?php

declare(strict_types=1);

namespace App\Modules\SEO\Support;

use App\Modules\SEO\DTOs\SeoTags;
use App\Modules\SEO\Services\SeoManager;
use App\Modules\SEO\Services\StructuredData;

/**
 * Renders the resolved {@see SeoTags} as the HTML that goes in the document
 * head.
 *
 * Every value is escaped here, once; nothing that reaches this class is
 * trusted, including the workspace's own settings, because a settings screen is
 * just another input.
 */
class MetaTags
{
    public function __construct(protected SeoManager $manager) {}

    public function render(): string
    {
        $tags = $this->manager->current() ?? $this->fallback();

        $lines = [];

        if ($tags->description !== null) {
            $lines[] = $this->meta('description', $tags->description);
        }

        if ($tags->keywords !== null) {
            $lines[] = $this->meta('keywords', $tags->keywords);
        }

        $lines[] = $this->meta('robots', $tags->robots());

        if ($tags->canonicalUrl !== null) {
            $lines[] = '<link rel="canonical" href="'.e($tags->canonicalUrl).'">';
        }

        $lines[] = $this->property('og:type', $tags->ogType ?? 'website');
        $lines[] = $this->property('og:title', $tags->ogTitle ?? $tags->title);
        $lines[] = $this->property('og:site_name', (string) config('saas.brand.name'));

        if ($tags->ogDescription !== null) {
            $lines[] = $this->property('og:description', $tags->ogDescription);
        }

        if ($tags->ogImage !== null) {
            $lines[] = $this->property('og:image', $tags->ogImage);
        }

        if ($tags->canonicalUrl !== null) {
            $lines[] = $this->property('og:url', $tags->canonicalUrl);
        }

        $lines[] = $this->meta('twitter:card', $tags->twitterCard->value);

        if ($tags->twitterSite !== null) {
            $lines[] = $this->meta('twitter:site', $tags->twitterSite);
        }

        if ($tags->twitterTitle !== null) {
            $lines[] = $this->meta('twitter:title', $tags->twitterTitle);
        }

        if ($tags->twitterDescription !== null) {
            $lines[] = $this->meta('twitter:description', $tags->twitterDescription);
        }

        if ($tags->twitterImage !== null) {
            $lines[] = $this->meta('twitter:image', $tags->twitterImage);
        }

        if ($tags->structuredData !== []) {
            $lines[] = StructuredData::script($tags->structuredData);
        }

        return implode("\n        ", array_filter($lines));
    }

    /**
     * What an ordinary application page gets: no per-page metadata, but the
     * deployment-wide robots directive still has to be emitted, because that is
     * the switch that keeps a staging copy out of the index.
     */
    protected function fallback(): SeoTags
    {
        $defaults = $this->manager->defaults();

        return new SeoTags(
            title: (string) ($defaults['title'] ?? config('saas.brand.name')),
            description: $defaults['description'],
            robotsIndex: $defaults['indexable'],
            robotsFollow: $defaults['indexable'],
            ogImage: $defaults['og_image'],
            twitterSite: $defaults['twitter_handle'],
        );
    }

    protected function meta(string $name, string $content): string
    {
        return '<meta name="'.e($name).'" content="'.e($content).'">';
    }

    protected function property(string $property, string $content): string
    {
        return '<meta property="'.e($property).'" content="'.e($content).'">';
    }
}
