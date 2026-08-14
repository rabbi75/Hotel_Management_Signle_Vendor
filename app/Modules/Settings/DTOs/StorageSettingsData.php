<?php

declare(strict_types=1);

namespace App\Modules\Settings\DTOs;

use App\Modules\Settings\Support\SettingsSchema;

/**
 * File storage driver and cloud bucket credentials.
 */
final readonly class StorageSettingsData extends SettingsPanelData
{
    public static function group(): string
    {
        return SettingsSchema::GROUP_STORAGE;
    }
}
