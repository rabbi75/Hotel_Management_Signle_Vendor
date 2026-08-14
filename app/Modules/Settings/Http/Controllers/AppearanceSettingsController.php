<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\Actions\UpdateSettings;
use App\Modules\Settings\DTOs\AppearanceSettingsData;
use App\Modules\Settings\Http\Requests\UpdateAppearanceSettingsRequest;
use App\Modules\Settings\Support\BrandAssetStore;
use App\Modules\Settings\Support\SettingsSchema;
use App\Support\Enums\Theme;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Response;

class AppearanceSettingsController extends SettingsController
{
    public function __construct(
        SettingsRepository $settings,
        UpdateSettings $updateSettings,
        protected BrandAssetStore $assets,
    ) {
        parent::__construct($settings, $updateSettings);
    }

    public function index(): Response
    {
        return $this->panel('admin/settings/appearance', SettingsSchema::GROUP_APPEARANCE, [
            'themes' => Theme::options(),
        ]);
    }

    public function update(UpdateAppearanceSettingsRequest $request): RedirectResponse
    {
        /** @var array<string, mixed> $values */
        $values = $request->safe()->except($this->slotFields());

        return $this->persist(
            AppearanceSettingsData::fromArray(array_merge($values, $this->resolveAssets($request))),
            $request,
        );
    }

    /**
     * Turn the posted uploads into the `*_url` values the settings store holds.
     *
     * A slot that was neither uploaded nor explicitly cleared is left out of the
     * result entirely, so {@see UpdateSettings} never sees the key and the stored
     * value survives a save of the rest of the panel.
     *
     * @return array<string, string>
     */
    protected function resolveAssets(UpdateAppearanceSettingsRequest $request): array
    {
        $resolved = [];

        foreach (BrandAssetStore::SLOTS as $slot) {
            $field = $slot.'_url';
            $file = $request->file($slot);

            if ($file instanceof UploadedFile) {
                $this->assets->forget($this->currentUrl($field));
                $resolved[$field] = $this->assets->put($file, $slot);

                continue;
            }

            if ($request->boolean($slot.'_cleared')) {
                $this->assets->forget($this->currentUrl($field));
                $resolved[$field] = '';
            }
        }

        return $resolved;
    }

    /**
     * The stored URL for a field, read from the system scope rather than through
     * the fallback chain: replacing an asset must only delete the file this
     * panel itself put there, never one a company or user scope contributed.
     */
    protected function currentUrl(string $field): ?string
    {
        $value = $this->settings->getFrom(
            SettingsRepository::SCOPE_SYSTEM,
            null,
            SettingsSchema::GROUP_APPEARANCE.'.'.$field,
        );

        return is_string($value) ? $value : null;
    }

    /**
     * The request fields that carry uploads rather than settings values.
     *
     * @return list<string>
     */
    protected function slotFields(): array
    {
        $fields = [];

        foreach (BrandAssetStore::SLOTS as $slot) {
            $fields[] = $slot;
            $fields[] = $slot.'_cleared';
        }

        return $fields;
    }
}
