<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Requests;

use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMenuRequest extends FormRequest
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

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'location' => [
                'sometimes',
                'required',
                Rule::enum(MenuLocation::class),
                Rule::unique('menus', 'location')
                    ->where('company_id', current_company_id())
                    ->ignore($menu instanceof Menu ? $menu->id : null),
            ],
        ];
    }
}
