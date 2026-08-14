<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Requests;

use App\Modules\Blog\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_categories', 'slug')->where('company_id', current_company_id()),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('blog_categories', 'id')->where('company_id', current_company_id()),
            ],
        ];
    }
}
