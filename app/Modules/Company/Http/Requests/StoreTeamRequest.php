<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests;

use App\Modules\Company\Models\Team;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Team::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->teamRules();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function teamRules(): array
    {
        $companyId = current_company_id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'lead_id' => [
                'nullable',
                'integer',
                Rule::exists('company_user', 'user_id')->where('company_id', $companyId),
            ],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => [
                'integer',
                Rule::exists('company_user', 'user_id')->where('company_id', $companyId),
            ],
        ];
    }
}
