<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Company\Models\Company;
use App\Modules\SEO\Enums\TwitterCard;
use App\Modules\SEO\Models\SeoMeta;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<SeoMeta>
 */
class SeoMetaFactory extends Factory
{
    protected $model = SeoMeta::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'seoable_type' => 'post',
            'seoable_id' => 1,
            'title' => fake()->sentence(6),
            'description' => fake()->sentence(20),
            'keywords' => null,
            'canonical_url' => null,
            'robots_index' => true,
            'robots_follow' => true,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'twitter_card' => TwitterCard::SummaryLargeImage,
            'twitter_title' => null,
            'twitter_description' => null,
            'twitter_image' => null,
            'structured_data' => null,
        ];
    }

    public function for_(Model $model): static
    {
        return $this->state(fn (array $attributes): array => [
            'seoable_type' => $model->getMorphClass(),
            'seoable_id' => $model->getKey(),
            'company_id' => $model->getAttribute('company_id'),
        ]);
    }
}
