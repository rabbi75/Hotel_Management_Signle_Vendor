<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\DTOs\LocalizationSettingsData;
use App\Modules\Settings\Http\Requests\UpdateLocalizationSettingsRequest;
use App\Modules\Settings\Support\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class LocalizationSettingsController extends SettingsController
{
    public function index(): Response
    {
        return $this->panel('admin/settings/localization', SettingsSchema::GROUP_LOCALIZATION, [
            'locales' => config('saas.locales'),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(UpdateLocalizationSettingsRequest $request): RedirectResponse
    {
        return $this->persist(LocalizationSettingsData::fromRequest($request), $request);
    }
}
