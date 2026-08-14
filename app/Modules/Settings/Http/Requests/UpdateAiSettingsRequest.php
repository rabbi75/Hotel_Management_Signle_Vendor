<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Support\SettingsSchema;

class UpdateAiSettingsRequest extends SettingsRequest
{
    protected function permission(): string
    {
        return 'platform.settings.ai';
    }

    protected function group(): string
    {
        return SettingsSchema::GROUP_AI;
    }
}
