<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Requests;

use App\Modules\CMS\Enums\LinkTarget;
use App\Modules\CMS\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $menu = $this->route('menu');

        return $menu instanceof Menu && Gate::allows('update', $menu);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $menu = $this->route('menu');
        $menuId = $menu instanceof Menu ? $menu->id : 0;

        return [
            'label' => ['required', 'string', 'max:120'],

            // An item points at a page or at a literal URL, never at neither.
            'page_id' => [
                'nullable',
                'required_without:url',
                'integer',
                Rule::exists('pages', 'id')->where('company_id', current_company_id())->whereNull('deleted_at'),
            ],
            'url' => ['nullable', 'required_without:page_id', 'string', 'max:2048'],

            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('menu_items', 'id')->where('menu_id', $menuId),
            ],
            'target' => ['nullable', Rule::enum(LinkTarget::class)],
            'icon' => ['nullable', 'string', 'max:64'],
            'permission' => ['nullable', 'string', 'max:191'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
