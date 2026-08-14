<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Requests\V1;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateApiUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * An omitted key is left unchanged, so every rule here is `sometimes`.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
