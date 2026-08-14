<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Requests;

use App\Modules\Blog\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag
            ? Gate::allows('update', $tag)
            : Gate::allows('create', Tag::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tag = $this->route('tag');

        return [
            'name' => ['required', 'string', 'max:60'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:60',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_tags', 'slug')
                    ->where('company_id', current_company_id())
                    ->ignore($tag instanceof Tag ? $tag->id : null),
            ],
        ];
    }
}
