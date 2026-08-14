<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Platform\Models\Admin;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SendTestMailRequest extends FormRequest
{
    /** Named guard, for the reason given on {@see SettingsRequest::authorize()}. */
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        if ($admin instanceof Admin && $admin->can('platform.settings.mail')) {
            return true;
        }

        $user = $this->user('web');

        return $user instanceof User && $user->can('platform.settings.mail');
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
