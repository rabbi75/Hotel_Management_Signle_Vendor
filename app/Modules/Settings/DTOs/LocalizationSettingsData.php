<?php

declare(strict_types=1);

namespace App\Modules\Settings\DTOs;

use App\Modules\Settings\Support\SettingsSchema;

/**
 * Locale, timezone, currency and date formatting defaults.
 */
final readonly class LocalizationSettingsData extends SettingsPanelData
{
    public static function group(): string
    {
        return SettingsSchema::GROUP_LOCALIZATION;
    }
}
