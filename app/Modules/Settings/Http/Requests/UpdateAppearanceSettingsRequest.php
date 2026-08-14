<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Support\BrandAssetStore;
use App\Modules\Settings\Support\SettingsSchema;

class UpdateAppearanceSettingsRequest extends SettingsRequest
{
    protected function permission(): string
    {
        return 'platform.settings.general';
    }

    protected function group(): string
    {
        return SettingsSchema::GROUP_APPEARANCE;
    }

    /**
     * The brand asset uploads, plus the flag that distinguishes "I removed this
     * asset" from "I did not touch this asset".
     *
     * SVG is deliberately absent from every list. These files are written to the
     * `public` disk and served from the application's own origin, and an SVG can
     * carry an inline <script>; accepting one would turn the logo picker into
     * stored XSS against every page that renders the logo. Raster only.
     *
     * @return array<string, mixed>
     */
    protected function extraRules(): array
    {
        $rules = [];

        foreach (BrandAssetStore::SLOTS as $slot) {
            $rules[$slot] = $slot === 'favicon'
                ? ['nullable', 'file', 'mimes:png,ico', 'max:512']
                : ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];

            $rules[$slot.'_cleared'] = ['boolean'];
        }

        return $rules;
    }
}
