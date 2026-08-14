<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\DTOs\SecuritySettingsData;
use App\Modules\Settings\Http\Requests\UpdateSecuritySettingsRequest;
use App\Modules\Settings\Support\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class SecuritySettingsController extends SettingsController
{
    public function index(): Response
    {
        return $this->panel('admin/settings/security', SettingsSchema::GROUP_SECURITY);
    }

    public function update(UpdateSecuritySettingsRequest $request): RedirectResponse
    {
        return $this->persist(SecuritySettingsData::fromRequest($request), $request);
    }
}
