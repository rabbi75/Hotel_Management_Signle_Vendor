<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Requests;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('update', $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user()?->getAuthIdentifier()),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('Another account already uses this email address.'),
            'first_name.required' => __('Please tell us your first name.'),
            'last_name.required' => __('Please tell us your last name.'),
        ];
    }
}
