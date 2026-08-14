<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Support\SettingsSchema;

class UpdateSecuritySettingsRequest extends SettingsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'allowed_ips.*' => ['string', 'max:64'],
        ]);
    }

    protected function permission(): string
    {
        return 'platform.settings.security';
    }

    protected function group(): string
    {
        return SettingsSchema::GROUP_SECURITY;
    }
}
