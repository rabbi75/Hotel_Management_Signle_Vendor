<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\DTOs\ApiKeySettingsData;
use App\Modules\Settings\Http\Requests\UpdateApiKeySettingsRequest;
use App\Modules\Settings\Support\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Third-party credentials.
 *
 * Every value in this panel is encrypted at rest and is only ever sent to the
 * client as {@see SettingsSchema::MASK} plus an `is_set` flag; posting the mask
 * back leaves the stored credential untouched.
 */
class ApiKeySettingsController extends SettingsController
{
    public function index(): Response
    {
        return $this->panel('admin/settings/api-keys', SettingsSchema::GROUP_API_KEYS, [
            'providers' => ['pusher', 'google', 'facebook', 'openai'],
            'mask' => SettingsSchema::MASK,
        ]);
    }

    public function update(UpdateApiKeySettingsRequest $request): RedirectResponse
    {
        return $this->persist(ApiKeySettingsData::fromRequest($request), $request);
    }
}
