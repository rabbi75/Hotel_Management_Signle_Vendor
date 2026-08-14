<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Support\SettingsSchema;

class UpdateMailSettingsRequest extends SettingsRequest
{
    protected function permission(): string
    {
        return 'platform.settings.mail';
    }

    protected function group(): string
    {
        return SettingsSchema::GROUP_MAIL;
    }
}
