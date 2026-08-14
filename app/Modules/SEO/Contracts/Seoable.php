<?php

declare(strict_types=1);

namespace App\Modules\SEO\Contracts;

use App\Modules\SEO\Concerns\HasSeo;
use App\Modules\SEO\DTOs\SeoMetaData;
use App\Modules\SEO\Models\SeoMeta;

/**
 * A model that can carry search-engine metadata.
 *
 * The `seo*()` accessors are the model's own answer to "what would you say
 * about yourself if nobody overrode it" — the fallback layer beneath the stored
 * {@see SeoMeta} row and above the workspace defaults.
 *
 * The `seo()` relation itself is supplied by {@see HasSeo}
 * and deliberately left off this interface: its generic parameters are bound to
 * the declaring model, which an interface cannot express.
 */
interface Seoable
{
    /**
     * Create, replace or (with null) remove this subject's stored override.
     */
    public function saveSeo(?SeoMetaData $data): ?SeoMeta;

    public function seoTitle(): string;

    public function seoDescription(): ?string;

    public function seoImage(): ?string;

    public function seoUrl(): ?string;

    /**
     * The Open Graph object type, e.g. `article` or `website`.
     */
    public function seoType(): string;

    /**
     * The rendered body the scorer reads. Empty string when the subject has none.
     */
    public function seoBody(): string;
}
