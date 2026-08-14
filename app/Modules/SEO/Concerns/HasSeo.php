<?php

declare(strict_types=1);

namespace App\Modules\SEO\Concerns;

use App\Modules\SEO\DTOs\SeoMetaData;
use App\Modules\SEO\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Opts a model into the SEO module.
 *
 * Provides the relation plus sensible `seo*()` defaults derived from the
 * commonest column names, so a model usually only has to declare
 * `implements Seoable` and override what it names differently.
 *
 * @phpstan-require-extends Model
 */
trait HasSeo
{
    /**
     * Deleting the subject must take its metadata with it: an orphaned row
     * would be silently adopted by whatever model later reuses the id.
     */
    public static function bootHasSeo(): void
    {
        static::deleted(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting() !== true) {
                return;
            }

            SeoMeta::query()
                ->withoutGlobalScopes()
                ->where('seoable_type', $model->getMorphClass())
                ->where('seoable_id', $model->getKey())
                ->delete();
        });
    }

    /**
     * @return MorphOne<SeoMeta, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /**
     * Create or update this model's metadata row.
     *
     * Passing null removes the override entirely, which is different from
     * passing an all-null payload: the former forgets that the author ever
     * opened the panel, the latter records "explicitly nothing".
     */
    public function saveSeo(?SeoMetaData $data): ?SeoMeta
    {
        if (! $data instanceof SeoMetaData) {
            SeoMeta::query()
                ->where('seoable_type', $this->getMorphClass())
                ->where('seoable_id', $this->getKey())
                ->delete();

            $this->unsetRelation('seo');

            return null;
        }

        $meta = $this->seo()->firstOrNew([]);
        $meta->fill($data->toAttributes());

        $companyId = $this->getAttribute('company_id') ?? current_company_id();

        if ($meta->getAttribute('company_id') === null && is_int($companyId)) {
            $meta->company_id = $companyId;
        }

        $this->seo()->save($meta);
        $this->setRelation('seo', $meta);

        return $meta;
    }

    public function seoTitle(): string
    {
        $title = $this->getAttribute('title') ?? $this->getAttribute('name') ?? '';

        return is_string($title) ? $title : '';
    }

    public function seoDescription(): ?string
    {
        foreach (['excerpt', 'summary', 'description'] as $attribute) {
            $value = $this->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    public function seoImage(): ?string
    {
        $value = $this->getAttribute('featured_image');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function seoUrl(): ?string
    {
        return null;
    }

    public function seoType(): string
    {
        return 'website';
    }

    public function seoBody(): string
    {
        $value = $this->getAttribute('body_html') ?? $this->getAttribute('body') ?? '';

        return is_string($value) ? $value : '';
    }
}
