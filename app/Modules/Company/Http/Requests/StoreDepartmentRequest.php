<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests;

use App\Modules\Company\Models\Department;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Department::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                'nullable',
                'integer',
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
}
