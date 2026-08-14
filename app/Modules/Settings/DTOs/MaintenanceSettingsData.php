<?php

declare(strict_types=1);

namespace App\Modules\Settings\DTOs;

use App\Modules\Settings\Support\SettingsSchema;

/**
 * Maintenance window, bypass secret and IP allow-list.
 */
final readonly class MaintenanceSettingsData extends SettingsPanelData
{
    public static function group(): string
    {
        return SettingsSchema::GROUP_MAINTENANCE;
    }
}
