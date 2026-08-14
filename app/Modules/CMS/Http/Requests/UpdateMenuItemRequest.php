<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Requests;

use App\Modules\CMS\Enums\LinkTarget;
use App\Modules\CMS\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof MenuItem && Gate::allows('update', $item->menu()->firstOrFail());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $menuId = $item instanceof MenuItem ? $item->menu_id : 0;

        return [
            'label' => ['sometimes', 'required', 'string', 'max:120'],
            'page_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('pages', 'id')->where('company_id', current_company_id())->whereNull('deleted_at'),
            ],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('menu_items', 'id')->where('menu_id', $menuId),
            ],
            'target' => ['sometimes', 'nullable', Rule::enum(LinkTarget::class)],
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'permission' => ['sometimes', 'nullable', 'string', 'max:191'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * An item may not be re-parented onto itself.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $item = $this->route('item');
            $parentId = $this->input('parent_id');

            if ($item instanceof MenuItem && is_numeric($parentId) && (int) $parentId === $item->id) {
                $validator->errors()->add('parent_id', __('A menu item cannot be its own parent.'));
            }
        });
    }
}
