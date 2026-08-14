<?php

declare(strict_types=1);

namespace App\Modules\SEO\Http\Resources;

use App\Modules\SEO\Models\SeoMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeoMeta
 */
class SeoMetaResource extends JsonResource
{
    /**
     * The shape the React SEO panel binds to. Absent values stay null rather
     * than being resolved to their defaults here: the panel shows the default
     * as placeholder text, and it can only do that if it can tell the
     * difference between "unset" and "set to the same thing".
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeoMeta $meta */
        $meta = $this->resource;

        return [
            'title' => $meta->title,
            'description' => $meta->description,
            'keywords' => $meta->keywords,
            'canonical_url' => $meta->canonical_url,
            'robots_index' => $meta->robots_index,
            'robots_follow' => $meta->robots_follow,
            'og_title' => $meta->og_title,
            'og_description' => $meta->og_description,
            'og_image' => $meta->og_image,
            'twitter_card' => $meta->twitter_card->value,
            'twitter_title' => $meta->twitter_title,
            'twitter_description' => $meta->twitter_description,
            'twitter_image' => $meta->twitter_image,
            'structured_data' => $meta->structured_data,
        ];
    }

    /**
     * The same shape with every field null — what a subject that has never been
     * edited sends, so the client never has to branch on a missing object.
     *
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return [
            'title' => null,
            'description' => null,
            'keywords' => null,
            'canonical_url' => null,
            'robots_index' => true,
            'robots_follow' => true,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'twitter_card' => 'summary_large_image',
            'twitter_title' => null,
            'twitter_description' => null,
            'twitter_image' => null,
            'structured_data' => null,
        ];
    }
}
