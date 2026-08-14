<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\DTOs\GeneralSettingsData;
use App\Modules\Settings\Http\Requests\UpdateGeneralSettingsRequest;
use App\Modules\Settings\Support\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * The landing panel of the settings area.
 */
class GeneralSettingsController extends SettingsController
{
    public function index(): Response
    {
        return $this->panel('admin/settings/general', SettingsSchema::GROUP_GENERAL);
    }

    public function update(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        return $this->persist(GeneralSettingsData::fromRequest($request), $request);
    }
}
