<?php

declare(strict_types=1);

namespace App\Modules\SEO\Http\Requests;

use App\Modules\SEO\Models\SeoMeta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSeoSettingsRequest extends FormRequest
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
            'default_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'default_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'title_suffix' => ['sometimes', 'nullable', 'string', 'max:80'],
            'title_max' => ['required', 'integer', 'min:20', 'max:120'],
            'description_max' => ['required', 'integer', 'min:50', 'max:320'],
            'default_og_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'twitter_handle' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^@[A-Za-z0-9_]{1,15}$/'],
            'robots_indexable' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'twitter_handle.regex' => __('A handle looks like @example — letters, numbers and underscores only.'),
        ];
    }
}
