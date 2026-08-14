<?php

declare(strict_types=1);

namespace App\Modules\Search\Services;

use App\Modules\Search\Contracts\SearchProvider;
use App\Modules\User\Models\User;

/**
 * Fans a term out across the providers a user may use.
 *
 * Providers are registered rather than hard-coded so a module can make its own
 * records findable; the aggregator's only jobs are permission filtering,
 * capping each group, and producing one consistent payload shape.
 */
class SearchAggregator
{
    /**
     * Shortest term worth running. Anything shorter matches most of the table
     * and costs a full scan to say so.
     */
    public const MIN_TERM_LENGTH = 2;

    public const DEFAULT_PER_GROUP = 5;

    /** @var array<string, SearchProvider> */
    protected array $providers = [];

    public function register(SearchProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    /**
     * @param  list<SearchProvider>  $providers
     */
    public function registerMany(array $providers): void
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * @return array<string, SearchProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * The providers this user is permitted to search.
     *
     * @return array<string, SearchProvider>
     */
    public function for(User $user): array
    {
        return array_filter($this->providers, static function (SearchProvider $provider) use ($user): bool {
            $permission = $provider->permission();

            return $permission === null || $user->can($permission);
        });
    }

    /**
     * Run a search and return the results grouped by provider.
     *
     * @param  list<string>|null  $only  Restrict to these provider keys.
     * @return array{term: string, total: int, groups: list<array{key: string, label: string, icon: string, items: list<array<string, mixed>>}>}
     */
    public function search(User $user, string $term, int $perGroup = self::DEFAULT_PER_GROUP, ?array $only = null): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return ['term' => $term, 'total' => 0, 'groups' => []];
        }

        $groups = [];
        $total = 0;

        foreach ($this->for($user) as $key => $provider) {
            if ($only !== null && ! in_array($key, $only, true)) {
                continue;
            }

            $items = $provider->search($term, $perGroup)
                ->take($perGroup)
                ->map(static fn (mixed $model): array => $provider->toResult($model))
                ->values()
                ->all();

            if ($items === []) {
                continue;
            }

            $total += count($items);

            $groups[] = [
                'key' => $provider->key(),
                'label' => $provider->label(),
                'icon' => $provider->icon(),
                'items' => $items,
            ];
        }

        return ['term' => $term, 'total' => $total, 'groups' => $groups];
    }
}
