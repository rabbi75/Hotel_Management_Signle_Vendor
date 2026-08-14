<?php

declare(strict_types=1);

namespace App\Support\Branding;

use App\Modules\Settings\Support\SettingsSchema;
use App\Support\Settings\SettingsRepository;

/**
 * The installation's brand identity, as every surface sees it.
 *
 * One place resolves the name and the five asset slots so the topbars, the auth
 * screens, the landing page and the document head cannot drift apart. Values
 * come from the system scope only: branding is what the operator chose for the
 * whole installation, and letting a company or user scope override it would let
 * a tenant restyle the operator console.
 */
class Branding
{
    public function __construct(protected SettingsRepository $settings) {}

    /**
     * @return array{
     *     name: string,
     *     short_name: string,
     *     tagline: string|null,
     *     logo: string|null,
     *     dark_logo: string|null,
     *     icon: string|null,
     *     favicon: string|null,
     *     landing_logo: string|null,
     *     primary_color: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->text('general.app_name') ?? (string) config('saas.brand.name'),
            'short_name' => $this->text('general.short_name') ?? (string) config('saas.brand.short_name'),
            'tagline' => $this->text('general.tagline'),
            'logo' => $this->text('appearance.logo_url'),
            'dark_logo' => $this->text('appearance.dark_logo_url'),
            'icon' => $this->text('appearance.icon_url'),
            'favicon' => $this->text('appearance.favicon_url'),
            'landing_logo' => $this->text('appearance.landing_logo_url'),
            'primary_color' => $this->text('appearance.primary_color')
                ?? (string) SettingsSchema::defaults()['appearance.primary_color'],
        ];
    }

    public function name(): string
    {
        return $this->toArray()['name'];
    }

    public function favicon(): ?string
    {
        return $this->text('appearance.favicon_url');
    }

    /**
     * A stored string, or null when it is absent or blank. An empty string is
     * how the panel records "this asset was removed", and every caller wants
     * that to read as "no asset" rather than as a src of "".
     */
    protected function text(string $key): ?string
    {
        $value = $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, $key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
