<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Requests;

use App\Support\Enums\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
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
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('saas.locales')))],
            'theme' => ['required', Rule::enum(Theme::class)],
            'notifications' => ['nullable', 'array'],
            'notifications.*' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'timezone.timezone' => __('Please choose a valid timezone.'),
            'locale.in' => __('That language is not enabled for this application.'),
            'notifications.*.boolean' => __('Notification preferences must be switched on or off.'),
        ];
    }
}
