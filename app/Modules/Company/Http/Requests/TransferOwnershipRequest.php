<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests;

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $company = current_company();

        return $user instanceof User
            && $company instanceof Company
            && $user->can('transferOwnership', $company);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('company_user', 'user_id')->where('company_id', current_company_id()),
            ],
        ];
    }
}
