<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $company = current_company();

        return $user instanceof User
            && $company instanceof Company
            && $user->can('inviteMembers', $company);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (CompanyRole $role): string => $role->value,
                    array_filter(CompanyRole::cases(), static fn (CompanyRole $role): bool => $role !== CompanyRole::Owner),
                )),
            ],
            'permission_roles' => ['sometimes', 'array'],
            'permission_roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
