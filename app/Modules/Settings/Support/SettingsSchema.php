<?php

declare(strict_types=1);

namespace App\Modules\Settings\Support;

use App\Modules\Settings\Actions\UpdateSettings;
use App\Support\Settings\SettingsRepository;
use App\Support\Theme\SidebarPalette;
use Illuminate\Support\Arr;

/**
 * The single declarative definition of every application setting.
 *
 * Controllers render from it, {@see UpdateSettings}
 * uses it as the write whitelist, the form requests derive their rules from it
 * and the seeder plants its defaults. Adding a key anywhere else is a bug: an
 * undeclared key can never be written.
 *
 * @phpstan-type Definition array{group: string, type: string, default: mixed, encrypted: bool, rules: list<string>}
 */
final class SettingsSchema
{
    /**
     * Placeholder returned to the client in place of a stored secret. A payload
     * that comes back unchanged is treated as "leave this secret alone".
     */
    public const MASK = '********';

    public const GROUP_GENERAL = 'general';

    public const GROUP_LOCALIZATION = 'localization';

    public const GROUP_MAIL = 'mail';

    public const GROUP_STORAGE = 'storage';

    public const GROUP_API_KEYS = 'api_keys';

    public const GROUP_AI = 'ai';

    public const GROUP_APPEARANCE = 'appearance';

    /** Console-only: the sidebar palette of each panel. */
    public const GROUP_PANEL_THEME = 'panel_theme';

    public const GROUP_SECURITY = 'security';

    public const GROUP_MAINTENANCE = 'maintenance';

    /**
     * Every setting the application understands, keyed by its dotted setting key.
     *
     * @return array<string, Definition>
     */
    public static function all(): array
    {
        return array_merge(
            self::general(),
            self::localization(),
            self::mail(),
            self::storage(),
            self::apiKeys(),
            self::ai(),
            self::appearance(),
            self::panelTheme(),
            self::security(),
            self::maintenance(),
        );
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return [
            self::GROUP_GENERAL,
            self::GROUP_LOCALIZATION,
            self::GROUP_MAIL,
            self::GROUP_STORAGE,
            self::GROUP_API_KEYS,
            self::GROUP_AI,
            self::GROUP_APPEARANCE,
            self::GROUP_PANEL_THEME,
            self::GROUP_SECURITY,
            self::GROUP_MAINTENANCE,
        ];
    }

    /**
     * @return array<string, Definition>
     */
    public static function forGroup(string $group): array
    {
        return array_filter(
            self::all(),
            static fn (array $definition): bool => $definition['group'] === $group,
        );
    }

    /**
     * The write whitelist for a panel.
     *
     * @return list<string>
     */
    public static function keysFor(string $group): array
    {
        return array_keys(self::forGroup($group));
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function isEncrypted(string $key): bool
    {
        return self::all()[$key]['encrypted'] ?? false;
    }

    /**
     * @return list<string>
     */
    public static function encryptedKeys(?string $group = null): array
    {
        $definitions = $group === null ? self::all() : self::forGroup($group);

        return array_keys(array_filter(
            $definitions,
            static fn (array $definition): bool => $definition['encrypted'],
        ));
    }

    /**
     * Validation rules for a panel, keyed by the request field name.
     *
     * Request fields mirror the setting key with the group prefix removed, so
     * the `mail.host` setting is posted as `host`.
     *
     * @return array<string, list<string>>
     */
    public static function rulesFor(string $group): array
    {
        $rules = [];

        foreach (self::forGroup($group) as $key => $definition) {
            $rules[self::field($key)] = $definition['rules'];
        }

        return $rules;
    }

    /**
     * Declared defaults for every key, or for one group.
     *
     * @return array<string, mixed>
     */
    public static function defaults(?string $group = null): array
    {
        $definitions = $group === null ? self::all() : self::forGroup($group);

        return array_map(
            static fn (array $definition): mixed => $definition['default'],
            $definitions,
        );
    }

    /**
     * Persisted values for a panel, falling back to the declared defaults.
     *
     * @return array<string, mixed>
     */
    public static function values(string $group, SettingsRepository $settings, string $scope = SettingsRepository::SCOPE_SYSTEM, ?int $scopeId = null): array
    {
        $stored = $settings->all($scope, $scopeId);
        $values = [];

        foreach (self::forGroup($group) as $key => $definition) {
            $values[self::field($key)] = self::cast(
                $definition['type'],
                Arr::get($stored, $key, $definition['default']),
            );
        }

        return $values;
    }

    /**
     * The client-safe view of a panel: secrets are replaced by {@see self::MASK}
     * and accompanied by an `is_set` flag so the UI can show "configured"
     * without ever receiving the credential itself.
     *
     * @return array{values: array<string, mixed>, secrets: array<string, bool>}
     */
    public static function present(string $group, SettingsRepository $settings, string $scope = SettingsRepository::SCOPE_SYSTEM, ?int $scopeId = null): array
    {
        $values = self::values($group, $settings, $scope, $scopeId);
        $secrets = [];

        foreach (self::encryptedKeys($group) as $key) {
            $field = self::field($key);
            $isSet = is_string($values[$field] ?? null) && $values[$field] !== '';

            $secrets[$field] = $isSet;
            $values[$field] = $isSet ? self::MASK : null;
        }

        return ['values' => $values, 'secrets' => $secrets];
    }

    /**
     * Strip the group prefix from a setting key to get its form field name.
     */
    public static function field(string $key): string
    {
        return str($key)->after('.')->toString();
    }

    /**
     * Expand a panel's form payload back into fully qualified setting keys,
     * dropping anything the schema does not declare.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function qualify(string $group, array $input): array
    {
        $qualified = [];

        foreach (self::forGroup($group) as $key => $definition) {
            $field = self::field($key);

            if (! array_key_exists($field, $input)) {
                continue;
            }

            $qualified[$key] = self::cast($definition['type'], $input[$field]);
        }

        return $qualified;
    }

    /**
     * Coerce a raw value into the type the schema declares, so a checkbox
     * posted as "0" is stored as a real boolean rather than a truthy string.
     */
    public static function cast(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'int' => (int) $value,
            'array' => is_array($value) ? array_values($value) : [],
            default => is_scalar($value) ? (string) $value : $value,
        };
    }

    /**
     * @return array<string, Definition>
     */
    private static function general(): array
    {
        return [
            'general.app_name' => self::define(self::GROUP_GENERAL, 'string', (string) config('saas.brand.name'), false, ['required', 'string', 'max:120']),
            'general.short_name' => self::define(self::GROUP_GENERAL, 'string', (string) config('saas.brand.short_name'), false, ['required', 'string', 'max:60']),
            'general.tagline' => self::define(self::GROUP_GENERAL, 'string', '', false, ['nullable', 'string', 'max:180']),
            'general.support_email' => self::define(self::GROUP_GENERAL, 'string', (string) config('saas.brand.support_email'), false, ['required', 'email', 'max:180']),
            'general.contact_phone' => self::define(self::GROUP_GENERAL, 'string', '', false, ['nullable', 'string', 'max:32']),
            'general.address' => self::define(self::GROUP_GENERAL, 'text', '', false, ['nullable', 'string', 'max:500']),
            'general.registration_enabled' => self::define(self::GROUP_GENERAL, 'bool', (bool) config('saas.auth.registration_enabled'), false, ['boolean']),
        ];
    }

    /**
     * @return array<string, Definition>
     */
    private static function localization(): array
    {
        /** @var array<string, mixed> $locales */
        $locales = config('saas.locales', []);

        return [
            'localization.default_locale' => self::define(self::GROUP_LOCALIZATION, 'string', (string) config('saas.defaults.locale'), false, ['required', 'string', 'in:'.implode(',', array_keys($locales))]),
            'localization.default_timezone' => self::define(self::GROUP_LOCALIZATION, 'string', (string) config('saas.defaults.timezone'), false, ['required', 'string', 'timezone']),
            'localization.default_currency' => self::define(self::GROUP_LOCALIZATION, 'string', (string) config('saas.defaults.currency'), false, ['required', 'string', 'size:3']),
            'localization.date_format' => self::define(self::GROUP_LOCALIZATION, 'string', (string) config('saas.defaults.date_format'), false, ['required', 'string', 'max:32']),
            'localization.time_format' => self::define(self::GROUP_LOCALIZATION, 'string', (string) config('saas.defaults.time_format'), false, ['required', 'string', 'max:32']),
            'localization.week_starts_on' => self::define(self::GROUP_LOCALIZATION, 'int', 1, false, ['required', 'integer', 'between:0,6']),
            'localization.enabled_locales' => self::define(self::GROUP_LOCALIZATION, 'array', array_keys($locales), false, ['array']),
        ];
    }

    /**
     * @return array<string, Definition>
     */
    private static function mail(): array
    {
        return [
            'mail.mailer' => self::define(self::GROUP_MAIL, 'string', (string) config('mail.default'), false, ['required', 'string', 'in:smtp,ses,postmark,resend,sendmail,log,array']),
            'mail.host' => self::define(self::GROUP_MAIL, 'string', '', false, ['nullable', 'string', 'max:180']),
            'mail.port' => self::define(self::GROUP_MAIL, 'int', 587, false, ['nullable', 'integer', 'between:1,65535']),
            'mail.username' => self::define(self::GROUP_MAIL, 'string', '', false, ['nullable', 'string', 'max:180']),
            'mail.password' => self::define(self::GROUP_MAIL, 'string', '', true, ['nullable', 'string', 'max:500']),
            'mail.encryption' => self::define(self::GROUP_MAIL, 'string', 'tls', false, ['nullable', 'string', 'in:tls,ssl,none']),
            'mail.from_address' => self::define(self::GROUP_MAIL, 'string', (string) config('mail.from.address'), false, ['required', 'email', 'max:180']),
            'mail.from_name' => self::define(self::GROUP_MAIL, 'string', (string) config('mail.from.name'), false, ['required', 'string', 'max:120']),
        ];
    }

    /**
     * @return array<string, Definition>
     */
    private static function storage(): array
    {
        return [
            'storage.disk' => self::define(self::GROUP_STORAGE, 'string', (string) config('saas.uploads.disk'), false, ['required', 'string', 'in:local,public,s3,r2']),
            'storage.max_upload_kb' => self::define(self::GROUP_STORAGE, 'int', (int) config('saas.uploads.max_size_kb'), false, ['required', 'integer', 'between:64,1048576']),

            'storage.s3_key' => self::define(self::GROUP_STORAGE, 'string', '', true, ['nullable', 'string', 'max:255']),
            'storage.s3_secret' => self::define(self::GROUP_STORAGE, 'string', '', true, ['nullable', 'string', 'max:500']),
            'storage.s3_region' => self::define(self::GROUP_STORAGE, 'string', '', false, ['nullable', 'string', 'max:64']),
            'storage.s3_bucket' => self::define(self::GROUP_STORAGE, 'string', '', false, ['nullable', 'string', 'max:180']),
            'storage.s3_endpoint' => self::define(self::GROUP_STORAGE, 'string', '', false, ['nullable', 'url', 'max:255']),
            'storage.s3_use_path_style' => self::define(self::GROUP_STORAGE, 'bool', false, false, ['boolean']),

            'storage.r2_account_id' => self::define(self::GROUP_STORAGE, 'string', '', false, ['nullable', 'string', 'max:180']),
            'storage.r2_access_key_id' => self::define(self::GROUP_STORAGE, 'string', '', true, ['nullable', 'string', 'max:255']),
            'storage.r2_secret_access_key' => self::define(self::GROUP_STORAGE, 'string', '', true, ['nullable', 'string', 'max:500']),
            'storage.r2_bucket' => self::define(self::GROUP_STORAGE, 'string', '', false, ['nullable', 'string', 'max:180']),
            'storage.r2_public_url' => self::define(self::GROUP_STORAGE, 'string', '', false, ['nullable', 'url', 'max:255']),
        ];
    }

    /**
     * Third-party credentials. Every value here is encrypted at rest without
     * exception — even the "public" halves of a key pair, because leaking an
     * app id still tells an attacker which tenant to target.
     *
     * @return array<string, Definition>
     */
    private static function apiKeys(): array
    {
        return [
            'api_keys.pusher_app_id' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:120']),
            'api_keys.pusher_key' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:255']),
            'api_keys.pusher_secret' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:500']),
            'api_keys.pusher_cluster' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:32']),

            'api_keys.google_client_id' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:255']),
            'api_keys.google_client_secret' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:500']),
            'api_keys.google_maps_key' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:255']),

            'api_keys.facebook_app_id' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:120']),
            'api_keys.facebook_app_secret' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:500']),

            'api_keys.openai_api_key' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:500']),
            'api_keys.openai_organization' => self::define(self::GROUP_API_KEYS, 'string', '', true, ['nullable', 'string', 'max:120']),
        ];
    }

    /**
     * Platform-wide LLM credentials and defaults. Tenants may still bring their
     * own keys; when they do not, {@see \App\Modules\AI\Services\ProviderKeyStore}
     * falls through to these system values before the environment.
     *
     * @return array<string, Definition>
     */
    private static function ai(): array
    {
        $providers = implode(',', ['anthropic', 'openai', 'gemini', 'deepseek', 'grok']);

        return [
            'ai.enabled' => self::define(self::GROUP_AI, 'bool', (bool) config('saas.ai.enabled', true), false, ['boolean']),
            'ai.default_provider' => self::define(self::GROUP_AI, 'string', (string) config('saas.ai.default', 'anthropic'), false, ['required', 'string', 'in:'.$providers]),
            'ai.credits_enabled' => self::define(self::GROUP_AI, 'bool', (bool) config('saas.ai.credits.enabled', true), false, ['boolean']),
            'ai.monthly_credits' => self::define(self::GROUP_AI, 'int', (int) config('saas.ai.credits.monthly_allowance', 1000), false, ['required', 'integer', 'between:0,10000000']),
            'ai.allow_tenant_keys' => self::define(self::GROUP_AI, 'bool', true, false, ['boolean']),

            'ai.anthropic_api_key' => self::define(self::GROUP_AI, 'string', '', true, ['nullable', 'string', 'max:500']),
            'ai.openai_api_key' => self::define(self::GROUP_AI, 'string', '', true, ['nullable', 'string', 'max:500']),
            'ai.gemini_api_key' => self::define(self::GROUP_AI, 'string', '', true, ['nullable', 'string', 'max:500']),
            'ai.deepseek_api_key' => self::define(self::GROUP_AI, 'string', '', true, ['nullable', 'string', 'max:500']),
            'ai.grok_api_key' => self::define(self::GROUP_AI, 'string', '', true, ['nullable', 'string', 'max:500']),
        ];
    }

    /**
     * @return array<string, Definition>
     */
    private static function appearance(): array
    {
        return [
            'appearance.theme' => self::define(self::GROUP_APPEARANCE, 'string', 'system', false, ['required', 'string', 'in:light,dark,system']),
            'appearance.primary_color' => self::define(self::GROUP_APPEARANCE, 'string', '#2563eb', false, ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/']),
            'appearance.sidebar_variant' => self::define(self::GROUP_APPEARANCE, 'string', 'sidebar', false, ['required', 'string', 'in:sidebar,floating,inset']),
            'appearance.logo_url' => self::define(self::GROUP_APPEARANCE, 'string', '', false, ['nullable', 'string', 'max:500']),
            'appearance.dark_logo_url' => self::define(self::GROUP_APPEARANCE, 'string', '', false, ['nullable', 'string', 'max:500']),
            'appearance.icon_url' => self::define(self::GROUP_APPEARANCE, 'string', '', false, ['nullable', 'string', 'max:500']),
            'appearance.favicon_url' => self::define(self::GROUP_APPEARANCE, 'string', '', false, ['nullable', 'string', 'max:500']),
            'appearance.landing_logo_url' => self::define(self::GROUP_APPEARANCE, 'string', '', false, ['nullable', 'string', 'max:500']),
            'appearance.custom_css' => self::define(self::GROUP_APPEARANCE, 'text', '', false, ['nullable', 'string', 'max:20000']),
        ];
    }

    /**
     * The sidebar palette of each panel.
     *
     * A group of its own rather than two more `appearance.*` keys, because only
     * the operator console may write them: sharing the appearance group would
     * put them inside the tenant panel's rule set and its write whitelist, which
     * is a path from a workspace's settings screen to the console's own chrome.
     *
     * Values are preset keys from a fixed registry, never raw colours.
     *
     * @return array<string, Definition>
     */
    private static function panelTheme(): array
    {
        $rules = ['required', 'string', 'in:'.implode(',', SidebarPalette::keys())];

        return [
            'panel_theme.admin_sidebar' => self::define(self::GROUP_PANEL_THEME, 'string', SidebarPalette::DEFAULT, false, $rules),
            'panel_theme.app_sidebar' => self::define(self::GROUP_PANEL_THEME, 'string', SidebarPalette::DEFAULT, false, $rules),
        ];
    }

    /**
     * @return array<string, Definition>
     */
    private static function security(): array
    {
        return [
            'security.email_verification_required' => self::define(self::GROUP_SECURITY, 'bool', (bool) config('saas.auth.email_verification_required'), false, ['boolean']),
            'security.two_factor_enforced' => self::define(self::GROUP_SECURITY, 'bool', false, false, ['boolean']),
            'security.password_min_length' => self::define(self::GROUP_SECURITY, 'int', 12, false, ['required', 'integer', 'between:8,128']),
            'security.password_requires_symbols' => self::define(self::GROUP_SECURITY, 'bool', true, false, ['boolean']),
            'security.password_expires_days' => self::define(self::GROUP_SECURITY, 'int', 0, false, ['nullable', 'integer', 'between:0,3650']),
            'security.max_login_attempts' => self::define(self::GROUP_SECURITY, 'int', (int) config('saas.auth.max_login_attempts'), false, ['required', 'integer', 'between:1,50']),
            'security.session_lifetime_minutes' => self::define(self::GROUP_SECURITY, 'int', (int) config('session.lifetime'), false, ['required', 'integer', 'between:5,43200']),
            'security.force_https' => self::define(self::GROUP_SECURITY, 'bool', true, false, ['boolean']),
            'security.allowed_ips' => self::define(self::GROUP_SECURITY, 'array', [], false, ['array']),
        ];
    }

    /**
     * @return array<string, Definition>
     */
    private static function maintenance(): array
    {
        return [
            'maintenance.enabled' => self::define(self::GROUP_MAINTENANCE, 'bool', false, false, ['boolean']),
            'maintenance.message' => self::define(self::GROUP_MAINTENANCE, 'text', '', false, ['nullable', 'string', 'max:500']),
            'maintenance.retry_after' => self::define(self::GROUP_MAINTENANCE, 'int', 3600, false, ['nullable', 'integer', 'between:0,86400']),
            'maintenance.secret' => self::define(self::GROUP_MAINTENANCE, 'string', '', true, ['nullable', 'string', 'max:120']),
            'maintenance.allowed_ips' => self::define(self::GROUP_MAINTENANCE, 'array', [], false, ['array']),
        ];
    }

    /**
     * @param  list<string>  $rules
     * @return Definition
     */
    private static function define(string $group, string $type, mixed $default, bool $encrypted, array $rules): array
    {
        return [
            'group' => $group,
            'type' => $type,
            'default' => $default,
            'encrypted' => $encrypted,
            'rules' => $rules,
        ];
    }
}
