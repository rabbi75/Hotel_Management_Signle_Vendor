<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests;

use App\Modules\Company\Http\Requests\Concerns\CompanyProfileRules;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    use CompanyProfileRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Company::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->profileRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Give the workspace a name.'),
        ];
    }
}
