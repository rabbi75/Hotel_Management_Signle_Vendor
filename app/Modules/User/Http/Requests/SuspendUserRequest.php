<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuspendUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('suspend', $this->route('user')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('A suspension must record why the account was suspended.'),
            'reason.min' => __('Please give a slightly more descriptive reason.'),
        ];
    }
}
