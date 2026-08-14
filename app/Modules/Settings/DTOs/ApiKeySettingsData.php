<?php

declare(strict_types=1);

namespace App\Modules\Settings\DTOs;

use App\Modules\Settings\Support\SettingsSchema;

/**
 * Third-party integration credentials. Every value is encrypted at rest.
 */
final readonly class ApiKeySettingsData extends SettingsPanelData
{
    public static function group(): string
    {
        return SettingsSchema::GROUP_API_KEYS;
    }
}
