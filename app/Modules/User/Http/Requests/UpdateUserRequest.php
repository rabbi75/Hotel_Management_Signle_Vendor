<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Requests;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Http\Requests\Concerns\ValidatesRoleAssignment;
use App\Modules\User\Models\User;
use App\Support\Enums\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ValidatesRoleAssignment;

    public function authorize(): bool
    {
        return ($this->user()?->can('update', $this->route('user')) ?? false) && $this->mayAssignRoles();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($user instanceof User ? $user->id : null),
            ],
            'password' => ['nullable', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:32'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'company_role' => ['required', Rule::enum(CompanyRole::class)],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('saas.locales')))],
            'theme' => ['nullable', Rule::enum(Theme::class)],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in($this->assignableRoles())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('Another account already uses this email address.'),
            'roles.*.in' => __('One of the selected roles cannot be assigned.'),
            'timezone.timezone' => __('Please choose a valid timezone.'),
            'locale.in' => __('That language is not enabled for this application.'),
        ];
    }
}
