<?php

declare(strict_types=1);

namespace App\Modules\Settings\DTOs;

use App\Modules\Settings\Support\SettingsSchema;

/**
 * Platform AI providers, defaults, and credit policy.
 */
final readonly class AiSettingsData extends SettingsPanelData
{
    public static function group(): string
    {
        return SettingsSchema::GROUP_AI;
    }
}
