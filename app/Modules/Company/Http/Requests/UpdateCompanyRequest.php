<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Requests;

use App\Modules\Company\Http\Requests\Concerns\CompanyProfileRules;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    use CompanyProfileRules;

    public function authorize(): bool
    {
        $user = $this->user();
        $company = $this->route('company');

        return $user instanceof User
            && $company instanceof Company
            && $user->can('update', $company);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'logo' => [
                'nullable',
                'image',
                'mimes:'.implode(',', (array) config('saas.uploads.image_mimes')),
                'max:'.(int) config('saas.uploads.avatar_max_size_kb'),
            ],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }
}
