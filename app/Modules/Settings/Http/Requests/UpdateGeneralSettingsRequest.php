<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Support\SettingsSchema;

class UpdateGeneralSettingsRequest extends SettingsRequest
{
    protected function permission(): string
    {
        return 'platform.settings.general';
    }

    protected function group(): string
    {
        return SettingsSchema::GROUP_GENERAL;
    }
}
