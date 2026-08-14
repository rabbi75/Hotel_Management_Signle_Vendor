<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Platform\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;

class SendTestMailRequest extends FormRequest
{
    /** Named guard, for the reason given on {@see SettingsRequest::authorize()}. */
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        return $admin instanceof Admin && $admin->can('platform.settings.mail');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient' => ['nullable', 'email', 'max:180'],
        ];
    }
}
