<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Requests;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderKeysRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('ai.providers.manage');
    }

    /**
     * A blank or masked value means "leave the stored key alone"; the store
     * refuses to write either, so a key can never be cleared by accident.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keys' => ['required', 'array'],
            'keys.*' => ['nullable', 'string', 'max:500'],
        ];
    }
}
