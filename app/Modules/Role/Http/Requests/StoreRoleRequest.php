<?php

declare(strict_types=1);

namespace App\Modules\Role\Http\Requests;

use App\Modules\Role\Models\Permission;
use App\Modules\Role\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/',
                Rule::unique(Role::class, 'name')->where('guard_name', $this->guard()),
                Rule::notIn([config('permissions.super_admin_role', 'super-admin')]),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::declared())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('Role names may only contain lowercase letters, numbers and hyphens.'),
            'name.unique' => __('A role with this name already exists.'),
            'name.not_in' => __('That role name is reserved.'),
            'permissions.*.in' => __('One of the selected permissions is not part of the permission registry.'),
        ];
    }

    public function guard(): string
    {
        return (string) config('auth.defaults.guard', 'web');
    }
}
