<?php

declare(strict_types=1);

namespace App\Modules\Settings\DTOs;

use App\Modules\Settings\Support\SettingsSchema;

/**
 * Password policy, session and authentication hardening.
 */
final readonly class SecuritySettingsData extends SettingsPanelData
{
    public static function group(): string
    {
        return SettingsSchema::GROUP_SECURITY;
    }
}
