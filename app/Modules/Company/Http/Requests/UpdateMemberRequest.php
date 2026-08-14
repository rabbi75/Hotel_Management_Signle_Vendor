<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $company = current_company();

        return $user instanceof User
            && $company instanceof Company
            && $user->can('updateMember', $company);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                // Owner is excluded: it is only reachable through an explicit
                // ownership transfer.
                Rule::in(array_map(
                    static fn (CompanyRole $role): string => $role->value,
                    array_filter(CompanyRole::cases(), static fn (CompanyRole $role): bool => $role !== CompanyRole::Owner),
                )),
            ],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')
                    ->where('company_id', current_company_id())
                    ->whereNull('deleted_at'),
            ],
            'job_title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
