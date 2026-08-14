<?php

declare(strict_types=1);

namespace App\Modules\SEO\Models;

use App\Modules\SEO\Concerns\HasSeo;
use App\Modules\SEO\Enums\TwitterCard;
use App\Modules\SEO\Services\SeoManager;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\SeoMetaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Search-engine metadata attached to any model that opts into
 * {@see HasSeo}.
 *
 * Every column is nullable on purpose: a blank field means "inherit the
 * workspace default" rather than "publish an empty tag", and the difference is
 * resolved by {@see SeoManager}, never by the model.
 *
 * @property int $id
 * @property int $company_id
 * @property string $seoable_type
 * @property int $seoable_id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $keywords
 * @property string|null $canonical_url
 * @property bool $robots_index
 * @property bool $robots_follow
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property TwitterCard $twitter_card
 * @property string|null $twitter_title
 * @property string|null $twitter_description
 * @property string|null $twitter_image
 * @property array<string, mixed>|null $structured_data
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SeoMeta extends Model
{
    /** @use HasFactory<SeoMetaFactory> */
    use BelongsToCompany, HasFactory;

    protected $table = 'seo_meta';

    protected $fillable = [
        'title',
        'description',
        'keywords',
        'canonical_url',
        'robots_index',
        'robots_follow',
        'og_title',
        'og_description',
        'og_image',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'structured_data',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The `robots` directive as a search engine expects to read it.
     */
    public function robots(): string
    {
        return implode(', ', [
            $this->robots_index ? 'index' : 'noindex',
            $this->robots_follow ? 'follow' : 'nofollow',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
            'twitter_card' => TwitterCard::class,
            'structured_data' => 'array',
        ];
    }
}
