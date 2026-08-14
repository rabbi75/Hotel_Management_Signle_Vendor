<?php

declare(strict_types=1);

namespace App\Modules\SEO\Services;

use App\Modules\SEO\Contracts\Seoable;
use App\Modules\SEO\DTOs\SeoTags;
use App\Modules\SEO\Enums\TwitterCard;
use App\Modules\SEO\Models\SeoMeta;
use App\Support\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the effective search-engine metadata for a page.
 *
 * Three layers, most specific first:
 *
 *   stored SeoMeta override  ->  the model's own seo*() accessors
 *                            ->  workspace settings / config('saas.seo.*')
 *
 * Registered as a singleton so a controller can {@see self::share()} the tags
 * it resolved and the view composer can pick them up without threading a
 * parameter through every response.
 */
class SeoManager
{
    protected ?SeoTags $shared = null;

    public function __construct(protected SettingsRepository $settings) {}

    /**
     * Workspace-level defaults, falling back to config('saas.seo.*').
     *
     * @return array{title: string|null, description: string|null, title_suffix: string|null, title_max: int, description_max: int, og_image: string|null, twitter_handle: string|null, indexable: bool}
     */
    public function defaults(): array
    {
        return [
            'title' => $this->stringSetting('seo.default_title'),
            'description' => $this->stringSetting('seo.default_description'),
            'title_suffix' => $this->stringSetting('seo.title_suffix') ?? (string) config('saas.seo.title_suffix'),
            'title_max' => (int) ($this->settings->get('seo.title_max') ?? 60),
            'description_max' => (int) ($this->settings->get('seo.description_max') ?? 160),
            'og_image' => $this->stringSetting('seo.default_og_image') ?? $this->configString('saas.seo.default_og_image'),
            'twitter_handle' => $this->stringSetting('seo.twitter_handle') ?? $this->configString('saas.seo.twitter_handle'),
            'indexable' => $this->indexable(),
        ];
    }

    /**
     * Whether this deployment wants to be in the index at all.
     *
     * Defaults to false so a staging copy is never accidentally crawled; every
     * per-page `index` directive is subordinate to it.
     */
    public function indexable(): bool
    {
        $stored = $this->settings->get('seo.robots_indexable');

        if ($stored === null) {
            return (bool) config('saas.seo.robots_indexable', false);
        }

        return filter_var($stored, FILTER_VALIDATE_BOOL);
    }

    public function titleMax(): int
    {
        return (int) ($this->settings->get('seo.title_max') ?? 60);
    }

    public function descriptionMax(): int
    {
        return (int) ($this->settings->get('seo.description_max') ?? 160);
    }

    /**
     * Resolve the head for a single model.
     *
     * @param  list<array<string, mixed>>  $extraGraphs
     */
    public function resolve(Seoable&Model $model, array $extraGraphs = []): SeoTags
    {
        $meta = $this->metaFor($model);
        $defaults = $this->defaults();

        $title = $this->firstFilled($meta?->title, $model->seoTitle(), $defaults['title']) ?? $defaults['title_suffix'] ?? '';
        $description = $this->firstFilled($meta?->description, $model->seoDescription(), $defaults['description']);
        $image = $this->firstFilled($meta?->og_image, $model->seoImage(), $defaults['og_image']);

        /** @var list<array<string, mixed>> $stored */
        $stored = $meta?->structured_data === null ? [] : [$meta->structured_data];

        // Read off the model before the ?? chains below: PHPStan rejects a
        // nullsafe access on the left-hand side of ??.
        $robotsIndex = $meta === null || $meta->robots_index;
        $robotsFollow = $meta === null || $meta->robots_follow;
        $twitterCard = $meta?->twitter_card;

        return new SeoTags(
            title: $this->withSuffix($title, $defaults['title_suffix']),
            description: $description,
            keywords: $meta?->keywords,
            canonicalUrl: $this->firstFilled($meta?->canonical_url, $model->seoUrl()),
            // A per-page `index` is always subordinate to the deployment-wide
            // switch: a staging copy must stay out of the index whatever any
            // individual post asks for.
            robotsIndex: $defaults['indexable'] && $robotsIndex,
            robotsFollow: $robotsFollow,
            ogTitle: $this->firstFilled($meta?->og_title, $title),
            ogDescription: $this->firstFilled($meta?->og_description, $description),
            ogImage: $image,
            ogType: $model->seoType(),
            twitterCard: $twitterCard ?? TwitterCard::SummaryLargeImage,
            twitterTitle: $this->firstFilled($meta?->twitter_title, $title),
            twitterDescription: $this->firstFilled($meta?->twitter_description, $description),
            twitterImage: $this->firstFilled($meta?->twitter_image, $image),
            twitterSite: $defaults['twitter_handle'],
            structuredData: [...$stored, ...$extraGraphs],
        );
    }

    /**
     * Resolve the head for a page that has no model behind it.
     *
     * @param  list<array<string, mixed>>  $graphs
     */
    public function forPage(string $title, ?string $description = null, ?string $url = null, array $graphs = []): SeoTags
    {
        $defaults = $this->defaults();

        return new SeoTags(
            title: $this->withSuffix($title, $defaults['title_suffix']),
            description: $description ?? $defaults['description'],
            canonicalUrl: $url,
            robotsIndex: $defaults['indexable'],
            ogTitle: $title,
            ogDescription: $description ?? $defaults['description'],
            ogImage: $defaults['og_image'],
            twitterTitle: $title,
            twitterDescription: $description ?? $defaults['description'],
            twitterImage: $defaults['og_image'],
            twitterSite: $defaults['twitter_handle'],
            structuredData: $graphs,
        );
    }

    /**
     * The stored override for a model, or null when it has never been edited.
     */
    public function metaFor(Seoable&Model $model): ?SeoMeta
    {
        if ($model->relationLoaded('seo')) {
            $loaded = $model->getRelation('seo');

            return $loaded instanceof SeoMeta ? $loaded : null;
        }

        return SeoMeta::query()
            ->where('seoable_type', $model->getMorphClass())
            ->where('seoable_id', $model->getKey())
            ->first();
    }

    public function share(SeoTags $tags): void
    {
        $this->shared = $tags;
    }

    public function current(): ?SeoTags
    {
        return $this->shared;
    }

    public function forget(): void
    {
        $this->shared = null;
    }

    protected function withSuffix(string $title, ?string $suffix): string
    {
        $title = trim($title);

        if ($suffix === null || $suffix === '' || $title === '' || str_contains($title, $suffix)) {
            return $title === '' ? (string) $suffix : $title;
        }

        return "{$title} — {$suffix}";
    }

    protected function firstFilled(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function stringSetting(string $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    protected function configString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
