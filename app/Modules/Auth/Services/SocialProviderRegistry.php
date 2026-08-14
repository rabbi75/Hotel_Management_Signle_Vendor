<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Contracts\SocialProvider;

/**
 * The set of OAuth providers this installation offers.
 *
 * Drivers are registered by key so an application can swap the default
 * Socialite-backed driver for its own implementation, or add a provider the
 * kit has never heard of, without touching the controller.
 */
class SocialProviderRegistry
{
    /**
     * Presentation metadata for the providers the kit ships with.
     *
     * @var array<string, array{label: string, icon: string}>
     */
    protected const KNOWN = [
        'google' => ['label' => 'Google', 'icon' => 'google'],
        'github' => ['label' => 'GitHub', 'icon' => 'github'],
        'facebook' => ['label' => 'Facebook', 'icon' => 'facebook'],
    ];

    /** @var array<string, SocialProvider> */
    protected array $providers = [];

    public function __construct()
    {
        /** @var list<string> $enabled */
        $enabled = config('saas.auth.socials', []);

        foreach ($enabled as $key) {
            $meta = self::KNOWN[$key] ?? ['label' => ucfirst($key), 'icon' => $key];

            $this->register(new SocialiteProvider($key, $meta['label'], $meta['icon']));
        }
    }

    public function register(SocialProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->providers);
    }

    public function get(string $key): ?SocialProvider
    {
        return $this->providers[$key] ?? null;
    }

    /**
     * Every registered driver, configured or not.
     *
     * @return array<string, SocialProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Only the drivers whose credentials are actually present — the ones whose
     * routes may be registered and whose buttons may be shown.
     *
     * @return array<string, SocialProvider>
     */
    public function enabled(): array
    {
        return array_filter(
            $this->providers,
            static fn (SocialProvider $provider): bool => $provider->isConfigured(),
        );
    }

    /**
     * @return list<array{key: string, label: string, icon: string}>
     */
    public function toArray(): array
    {
        return array_values(array_map(
            static fn (SocialProvider $provider): array => [
                'key' => $provider->key(),
                'label' => $provider->label(),
                'icon' => $provider->icon(),
            ],
            $this->enabled(),
        ));
    }
}
