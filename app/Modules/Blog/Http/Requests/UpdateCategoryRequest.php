<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Requests;

use App\Modules\Blog\Models\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category && Gate::allows('update', $category);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $id = $category instanceof Category ? $category->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_categories', 'slug')->where('company_id', current_company_id())->ignore($id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('blog_categories', 'id')->where('company_id', current_company_id()),
                $this->notItsOwnDescendant($category instanceof Category ? $category : null),
            ],
        ];
    }

    /**
     * A category may not be moved beneath itself or one of its own children:
     * the resulting cycle makes the tree unrenderable and every ancestor walk
     * infinite.
     */
    protected function notItsOwnDescendant(?Category $category): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($category): void {
            if ($category === null || $value === null || $value === '') {
                return;
            }

            if (in_array((int) $value, $category->descendantIds(), true)) {
                $fail(__('A category cannot be placed inside itself or one of its own subcategories.'));
            }
        };
    }
}
