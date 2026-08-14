<?php

declare(strict_types=1);

namespace App\Modules\SEO\DTOs;

use App\Modules\SEO\Enums\TwitterCard;
use App\Support\DTOs\Data;

/**
 * The fully resolved head of one page: every value already merged with the
 * workspace defaults, so a renderer never has to know about fallbacks.
 */
readonly class SeoTags extends Data
{
    /**
     * @param  list<array<string, mixed>>  $structuredData  Zero or more JSON-LD graphs.
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $keywords = null,
        public ?string $canonicalUrl = null,
        public bool $robotsIndex = true,
        public bool $robotsFollow = true,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public ?string $ogType = 'website',
        public TwitterCard $twitterCard = TwitterCard::SummaryLargeImage,
        public ?string $twitterTitle = null,
        public ?string $twitterDescription = null,
        public ?string $twitterImage = null,
        public ?string $twitterSite = null,
        public array $structuredData = [],
    ) {}

    public function robots(): string
    {
        return implode(', ', [
            $this->robotsIndex ? 'index' : 'noindex',
            $this->robotsFollow ? 'follow' : 'nofollow',
        ]);
    }
}
