<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Support\SettingsSchema;

class UpdateLocalizationSettingsRequest extends SettingsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var array<string, mixed> $locales */
        $locales = config('saas.locales', []);

        return array_merge(parent::rules(), [
            'enabled_locales.*' => ['string', 'in:'.implode(',', array_keys($locales))],
        ]);
    }

    protected function permission(): string
    {
        return 'platform.settings.general';
    }

    protected function group(): string
    {
        return SettingsSchema::GROUP_LOCALIZATION;
    }
}
