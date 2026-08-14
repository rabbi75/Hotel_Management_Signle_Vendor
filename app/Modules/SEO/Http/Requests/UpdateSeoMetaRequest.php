<?php

declare(strict_types=1);

namespace App\Modules\SEO\Http\Requests;

use App\Modules\SEO\Models\SeoMeta;
use App\Modules\SEO\Support\SeoMetaTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Edits the metadata of an arbitrary seoable subject.
 *
 * The subject is addressed by morph alias plus id, and the alias is checked
 * against an allow list: without that, `type` is a free-text class name and the
 * endpoint becomes a way to attach rows to any model in the application.
 */
class UpdateSeoMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', SeoMeta::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(SeoMetaTypes::allowed()))],
            'id' => ['required', 'integer', 'min:1'],

            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'robots_index' => ['boolean'],
            'robots_follow' => ['boolean'],
            'og_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'og_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'og_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'twitter_card' => ['sometimes', 'nullable', 'string', 'in:summary,summary_large_image,app,player'],
            'twitter_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'twitter_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'twitter_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}
