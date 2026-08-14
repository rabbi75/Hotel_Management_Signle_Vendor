<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\DTOs\MaintenanceSettingsData;
use App\Modules\Settings\Http\Middleware\CheckMaintenanceMode;
use App\Modules\Settings\Http\Requests\UpdateMaintenanceSettingsRequest;
use App\Modules\Settings\Support\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

/**
 * Puts the application behind a maintenance screen.
 *
 * Unlike `artisan down`, the flag lives in the settings store: it survives a
 * deploy, is visible in the audit trail, and can be lifted from the UI by
 * anyone holding `settings.maintenance.toggle` without shell access.
 *
 * @see CheckMaintenanceMode
 */
class MaintenanceModeController extends SettingsController
{
    public function index(Request $request): Response
    {
        // The bypass token is write-only everywhere else. It is surfaced here,
        // once, on the redirect that immediately follows generating it — after
        // which the flash is gone and only its hash-like presence flag remains.
        return $this->panel('admin/settings/maintenance', SettingsSchema::GROUP_MAINTENANCE, [
            'generatedSecret' => $request->session()->get('maintenance_secret'),
        ]);
    }

    public function enable(UpdateMaintenanceSettingsRequest $request): RedirectResponse
    {
        $input = $request->validated();
        $secret = is_string($input['secret'] ?? null) && $input['secret'] !== '' && $input['secret'] !== SettingsSchema::MASK
            ? $input['secret']
            : $this->existingOrNewSecret();

        $data = MaintenanceSettingsData::fromArray(array_merge($input, [
            'enabled' => true,
            'secret' => $secret,
        ]));

        $this->updateSettings->handle($data, $request->user());

        // Redirect to the panel rather than back(): the index route is what
        // reads `maintenance_secret` into a page prop, and a flash survives
        // exactly one request, so the token is displayed once and only once.
        return to_route('admin.settings.maintenance.index')
            ->with('success', __('Maintenance mode enabled.'))
            ->with('maintenance_secret', $secret);
    }

    public function disable(UpdateMaintenanceSettingsRequest $request): RedirectResponse
    {
        $this->updateSettings->handle(
            MaintenanceSettingsData::fromArray(['enabled' => false]),
            $request->user(),
        );

        return back()->with('success', __('Maintenance mode disabled.'));
    }

    protected function existingOrNewSecret(): string
    {
        $current = $this->settings->get('maintenance.secret');

        return is_string($current) && $current !== '' ? $current : Str::random(40);
    }
}
