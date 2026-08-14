<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Modules\Settings\Models\Setting;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;

/**
 * Read/write access to the layered application settings store.
 *
 * Values resolve through three scopes, most specific first:
 *
 *   user  ->  company  ->  system  ->  config('saas.*') fallback
 *
 * Each scope is cached as a single hash so a page render costs at most three
 * cache reads regardless of how many keys it touches.
 */
class SettingsRepository
{
    public const SCOPE_SYSTEM = 'system';

    public const SCOPE_COMPANY = 'company';

    public const SCOPE_USER = 'user';

    public function __construct(
        protected CacheRepository $cache,
        protected CurrentCompany $tenant,
    ) {}

    /**
     * Resolve a setting, walking scopes from most to least specific.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        foreach ($this->activeScopes() as [$scope, $scopeId]) {
            $values = $this->all($scope, $scopeId);

            if (Arr::has($values, $key)) {
                return Arr::get($values, $key);
            }
        }

        return $default ?? config("saas.{$key}");
    }

    /**
     * Read a setting from one specific scope, ignoring the fallback chain.
     */
    public function getFrom(string $scope, ?int $scopeId, string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all($scope, $scopeId), $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(string $scope = self::SCOPE_SYSTEM, ?int $scopeId = null): array
    {
        return $this->cache->remember(
            $this->cacheKey($scope, $scopeId),
            (int) config('saas.cache.settings_ttl'),
            fn (): array => Setting::query()
                ->withoutGlobalScopes()
                ->where('scope', $scope)
                ->where('scope_id', $scopeId)
                ->get()
                ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group, string $scope = self::SCOPE_SYSTEM, ?int $scopeId = null): array
    {
        return Arr::where(
            $this->all($scope, $scopeId),
            static fn (mixed $value, string $key): bool => str_starts_with($key, $group.'.'),
        );
    }

    public function set(string $key, mixed $value, string $scope = self::SCOPE_SYSTEM, ?int $scopeId = null, bool $encrypted = false): void
    {
        Setting::query()->withoutGlobalScopes()->updateOrCreate(
            ['scope' => $scope, 'scope_id' => $scopeId, 'key' => $key],
            ['is_encrypted' => $encrypted, 'value' => $value, 'group' => str($key)->before('.')->toString()],
        );

        $this->flush($scope, $scopeId);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $encryptedKeys
     */
    public function setMany(array $values, string $scope = self::SCOPE_SYSTEM, ?int $scopeId = null, array $encryptedKeys = []): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->withoutGlobalScopes()->updateOrCreate(
                ['scope' => $scope, 'scope_id' => $scopeId, 'key' => $key],
                [
                    // is_encrypted must be filled before value: Setting's value
                    // mutator reads the flag to decide whether to encrypt, and
                    // fill() applies attributes in array order.
                    'is_encrypted' => in_array($key, $encryptedKeys, true),
                    'value' => $value,
                    'group' => str($key)->before('.')->toString(),
                ],
            );
        }

        $this->flush($scope, $scopeId);
    }

    public function forget(string $key, string $scope = self::SCOPE_SYSTEM, ?int $scopeId = null): void
    {
        Setting::query()
            ->withoutGlobalScopes()
            ->where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->where('key', $key)
            ->delete();

        $this->flush($scope, $scopeId);
    }

    public function flush(string $scope, ?int $scopeId = null): void
    {
        $this->cache->forget($this->cacheKey($scope, $scopeId));
    }

    /**
     * Scopes to consult, in resolution order.
     *
     * @return list<array{0: string, 1: int|null}>
     */
    protected function activeScopes(): array
    {
        $scopes = [];

        if ($userId = auth()->id()) {
            $scopes[] = [self::SCOPE_USER, (int) $userId];
        }

        if ($companyId = $this->tenant->id()) {
            $scopes[] = [self::SCOPE_COMPANY, $companyId];
        }

        $scopes[] = [self::SCOPE_SYSTEM, null];

        return $scopes;
    }

    protected function cacheKey(string $scope, ?int $scopeId): string
    {
        return "settings:{$scope}:".($scopeId ?? 'global');
    }
}
