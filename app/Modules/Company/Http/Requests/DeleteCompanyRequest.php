<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests;

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deleting a workspace is irreversible from the UI, so the owner has to retype
 * its exact name — a deliberate speed bump, on top of password confirmation.
 */
class DeleteCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $company = $this->route('company');

        return $user instanceof User
            && $company instanceof Company
            && $user->can('delete', $company);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $company = $this->route('company');

        return [
            'name' => [
                'required',
                'string',
                Rule::in([$company instanceof Company ? $company->name : null]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.in' => __('The workspace name does not match.'),
        ];
    }
}
