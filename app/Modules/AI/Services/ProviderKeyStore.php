<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Support\Settings\SettingsRepository;

/**
 * Provider credentials: company BYOK, then platform system keys, then env.
 *
 * Keys live in the encrypted settings store rather than only the environment so
 * an operator can provision shared credentials for every tenant, and a customer
 * can still bring their own. Credentials are never returned to the client: the
 * UI receives a mask plus an `is_set` flag and nothing else.
 */
class ProviderKeyStore
{
    public const MASK = '********';

    public function __construct(protected SettingsRepository $settings) {}

    public static function settingKey(string $provider): string
    {
        return "ai_providers.{$provider}_key";
    }

    public static function systemSettingKey(string $provider): string
    {
        return "ai.{$provider}_api_key";
    }

    /**
     * The key to authenticate with: the workspace's own (when allowed), then
     * the platform-wide system credential, then config/env.
     */
    public function resolve(string $provider, ?int $companyId = null): ?string
    {
        $companyId ??= current_company_id();

        if ($companyId !== null && $this->tenantKeysAllowed()) {
            $stored = $this->settings->getFrom(SettingsRepository::SCOPE_COMPANY, $companyId, self::settingKey($provider));

            if (is_string($stored) && $stored !== '') {
                return $stored;
            }
        }

        $system = $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, self::systemSettingKey($provider));

        if (is_string($system) && $system !== '') {
            return $system;
        }

        // Legacy OpenAI key from the generic API-keys panel.
        if ($provider === 'openai') {
            $legacy = $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'api_keys.openai_api_key');

            if (is_string($legacy) && $legacy !== '') {
                return $legacy;
            }
        }

        $fallback = config("saas.ai.providers.{$provider}.key");

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    public function isSet(string $provider, ?int $companyId = null): bool
    {
        return $this->resolve($provider, $companyId) !== null;
    }

    /**
     * Whether tenants may store their own provider keys (BYOK).
     */
    public function tenantKeysAllowed(): bool
    {
        $value = $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'ai.allow_tenant_keys');

        if ($value === null) {
            return true;
        }

        return (bool) $value;
    }

    /**
     * A blank submitted field means "leave the stored key alone", never
     * "clear it" — otherwise every save of an unrelated field would wipe the
     * credential the form never showed.
     */
    public function put(string $provider, ?string $key, ?int $companyId = null): void
    {
        $companyId ??= current_company_id();

        if ($companyId === null || $key === null || $key === '' || $key === self::MASK) {
            return;
        }

        if (! $this->tenantKeysAllowed()) {
            return;
        }

        $this->settings->set(self::settingKey($provider), $key, SettingsRepository::SCOPE_COMPANY, $companyId, true);
    }

    public function forget(string $provider, ?int $companyId = null): void
    {
        $companyId ??= current_company_id();

        if ($companyId !== null) {
            $this->settings->forget(self::settingKey($provider), SettingsRepository::SCOPE_COMPANY, $companyId);
        }
    }
}
