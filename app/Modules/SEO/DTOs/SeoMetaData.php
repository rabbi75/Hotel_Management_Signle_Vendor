<?php

declare(strict_types=1);

namespace App\Modules\SEO\DTOs;

use App\Modules\SEO\Enums\TwitterCard;
use App\Modules\SEO\Models\SeoMeta;
use App\Support\DTOs\Data;

/**
 * The editable half of a {@see SeoMeta} row.
 *
 * Nulls are meaningful here: every optional field is written back even when
 * null, because clearing an override is how a page is returned to the workspace
 * default. Callers that only want to touch some fields must merge before
 * constructing, not rely on omission.
 */
readonly class SeoMetaData extends Data
{
    /**
     * @param  array<string, mixed>|null  $structuredData
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $keywords = null,
        public ?string $canonicalUrl = null,
        public bool $robotsIndex = true,
        public bool $robotsFollow = true,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public TwitterCard $twitterCard = TwitterCard::SummaryLargeImage,
        public ?string $twitterTitle = null,
        public ?string $twitterDescription = null,
        public ?string $twitterImage = null,
        public ?array $structuredData = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        /** @var array<string, mixed>|null $structured */
        $structured = is_array($input['structured_data'] ?? null) ? $input['structured_data'] : null;

        return new self(
            title: self::text($input, 'title'),
            description: self::text($input, 'description'),
            keywords: self::text($input, 'keywords'),
            canonicalUrl: self::text($input, 'canonical_url'),
            robotsIndex: filter_var($input['robots_index'] ?? true, FILTER_VALIDATE_BOOL),
            robotsFollow: filter_var($input['robots_follow'] ?? true, FILTER_VALIDATE_BOOL),
            ogTitle: self::text($input, 'og_title'),
            ogDescription: self::text($input, 'og_description'),
            ogImage: self::text($input, 'og_image'),
            twitterCard: TwitterCard::tryFrom((string) ($input['twitter_card'] ?? '')) ?? TwitterCard::SummaryLargeImage,
            twitterTitle: self::text($input, 'twitter_title'),
            twitterDescription: self::text($input, 'twitter_description'),
            twitterImage: self::text($input, 'twitter_image'),
            structuredData: $structured,
        );
    }

    /**
     * Every column, nulls included — see the class docblock.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'canonical_url' => $this->canonicalUrl,
            'robots_index' => $this->robotsIndex,
            'robots_follow' => $this->robotsFollow,
            'og_title' => $this->ogTitle,
            'og_description' => $this->ogDescription,
            'og_image' => $this->ogImage,
            'twitter_card' => $this->twitterCard,
            'twitter_title' => $this->twitterTitle,
            'twitter_description' => $this->twitterDescription,
            'twitter_image' => $this->twitterImage,
            'structured_data' => $this->structuredData,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function text(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
