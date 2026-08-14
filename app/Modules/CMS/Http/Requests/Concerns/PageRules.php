<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Requests\Concerns;

use App\Modules\CMS\Enums\PageStatus;
use App\Modules\CMS\Rules\NotReservedSlug;
use Illuminate\Validation\Rule;

/**
 * The page attribute rules shared by the create and edit requests.
 */
trait PageRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function pageRules(?int $ignoreId = null): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                new NotReservedSlug,
                Rule::unique('pages', 'slug')
                    ->where('company_id', current_company_id())
                    ->ignore($ignoreId)
                    ->whereNull('deleted_at'),
            ],
            'status' => ['nullable', Rule::enum(PageStatus::class)],
            'layout' => ['nullable', 'string', 'max:64'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('pages', 'id')
                    ->where('company_id', current_company_id())
                    ->whereNull('deleted_at'),
            ],
            'is_homepage' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'seo' => ['nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:'.(int) config('saas.seo.title_max', 60)],
            'seo.description' => ['nullable', 'string', 'max:'.(int) config('saas.seo.description_max', 160)],
            'seo.keywords' => ['nullable', 'string', 'max:500'],
            'seo.og_image' => ['nullable', 'string', 'max:2048'],
            'seo.canonical' => ['nullable', 'string', 'max:2048'],
            'seo.noindex' => ['nullable', 'boolean'],
        ];
    }
}
