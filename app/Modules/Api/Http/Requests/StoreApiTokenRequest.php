<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Requests;

use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Services\ApiTokenService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', ApiToken::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(array_keys(ApiTokenService::abilities()))],
            'expires_in_days' => ['nullable', 'integer', 'between:0,3650'],
        ];
    }
}
