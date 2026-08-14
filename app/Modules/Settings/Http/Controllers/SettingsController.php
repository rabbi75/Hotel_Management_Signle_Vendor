<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Actions\UpdateSettings;
use App\Modules\Settings\DTOs\SettingsPanelData;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Support\SettingsSchema;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

/**
 * Shared plumbing for the settings panels: every panel renders the same shape
 * of props and writes through the same audited action.
 */
abstract class SettingsController extends Controller
{
    public function __construct(
        protected SettingsRepository $settings,
        protected UpdateSettings $updateSettings,
    ) {}

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function panel(string $component, string $group, array $extra = []): Response
    {
        Gate::authorize('viewAny', Setting::class);

        $presented = SettingsSchema::present($group, $this->settings);

        return inertia($component, array_merge([
            'group' => $group,
            'settings' => $presented['values'],
            // Secrets are never sent back; the client only learns whether one
            // is configured so it can render "•••• (set)" next to the input.
            'secrets' => $presented['secrets'],
            'tabs' => SettingsSchema::groups(),
        ], $extra));
    }

    protected function persist(SettingsPanelData $data, Request $request, ?string $message = null): RedirectResponse
    {
        $changed = $this->updateSettings->handle($data, $request->user());

        return back()->with(
            'success',
            $message ?? ($changed === []
                ? __('No changes to save.')
                : __(':count setting(s) updated.', ['count' => count($changed)])),
        );
    }
}
