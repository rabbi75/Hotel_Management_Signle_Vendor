<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Support\SettingsSchema;

class UpdateApiKeySettingsRequest extends SettingsRequest
{
    protected function permission(): string
    {
        return 'platform.settings.api_keys';
    }

    protected function group(): string
    {
        return SettingsSchema::GROUP_API_KEYS;
    }
}
