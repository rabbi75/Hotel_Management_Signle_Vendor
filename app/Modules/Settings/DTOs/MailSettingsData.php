<?php

declare(strict_types=1);

namespace App\Modules\Settings\DTOs;

use App\Modules\Settings\Support\SettingsSchema;

/**
 * Outbound mail transport configuration.
 */
final readonly class MailSettingsData extends SettingsPanelData
{
    public static function group(): string
    {
        return SettingsSchema::GROUP_MAIL;
    }
}
