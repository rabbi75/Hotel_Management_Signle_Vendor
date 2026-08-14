<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests;

use App\Modules\Company\Models\Department;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $department = $this->route('department');

        return $user instanceof User
            && $department instanceof Department
            && $user->can('update', $department);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $department = $this->route('department');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::notIn([$department instanceof Department ? $department->id : null]),
                Rule::exists('departments', 'id')
                    ->where('company_id', current_company_id())
                    ->whereNull('deleted_at'),
            ],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('company_user', 'user_id')->where('company_id', current_company_id()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.not_in' => __('A department cannot be its own parent.'),
        ];
    }
}
