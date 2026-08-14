<?php

declare(strict_types=1);

namespace App\Modules\Settings\DTOs;

use App\Modules\Settings\Support\SettingsSchema;

/**
 * General branding and contact details.
 */
final readonly class GeneralSettingsData extends SettingsPanelData
{
    public static function group(): string
    {
        return SettingsSchema::GROUP_GENERAL;
    }
}
