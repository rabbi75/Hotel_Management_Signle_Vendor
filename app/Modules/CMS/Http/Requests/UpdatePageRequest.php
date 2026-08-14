<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Requests;

use App\Modules\CMS\Http\Requests\Concerns\PageRules;
use App\Modules\CMS\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdatePageRequest extends FormRequest
{
    use PageRules;

    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page && Gate::allows('update', $page);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $page = $this->route('page');

        return $this->pageRules($page instanceof Page ? $page->id : null);
    }

    /**
     * A page may not be its own ancestor; without this the tree query would
     * recurse forever.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $page = $this->route('page');
            $parentId = $this->input('parent_id');

            if ($page instanceof Page && is_numeric($parentId) && (int) $parentId === $page->id) {
                $validator->errors()->add('parent_id', __('A page cannot be its own parent.'));
            }
        });
    }
}
