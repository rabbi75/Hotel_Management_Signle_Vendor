<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Modules\Dashboard\Enums\WidgetSize;
use App\Modules\Dashboard\Widgets\Widget;
use App\Modules\Dashboard\Widgets\WidgetRegistry;
use App\Modules\User\Models\User;
use App\Support\Settings\SettingsRepository;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Assembles the dashboard for one user in one workspace.
 *
 * Widget payloads are cached per workspace *and* per user: the same widget
 * shows different numbers in a different workspace, and different rows to a
 * user with narrower permissions, so either key alone would leak.
 */
class DashboardService
{
    /**
     * Where a user's saved arrangement lives in the settings store.
     */
    public const LAYOUT_KEY = 'dashboard.layout';

    public function __construct(
        protected WidgetRegistry $registry,
        protected SettingsRepository $settings,
        protected CacheRepository $cache,
        protected CurrentCompany $tenant,
    ) {}

    /**
     * The rendered dashboard: every widget the user may see, in their order,
     * with its data attached.
     *
     * @return list<array<string, mixed>>
     */
    public function for(User $user): array
    {
        $layout = $this->layout($user);
        $widgets = [];

        // Widgets the user has never arranged sort after every widget they
        // have, keeping their own relative order. Interleaving the two by raw
        // order number would let a default slot push its way into the middle of
        // an arrangement the user made deliberately.
        $tail = (count($layout) + 1) * 10;

        foreach ($this->registry->for($user) as $widget) {
            $saved = $layout[$widget->key()] ?? null;

            $widgets[] = [
                ...$widget->toArray(),
                'size' => $saved['size'] ?? $widget->defaultSize()->value,
                'order' => $saved['order'] ?? $tail + $widget->defaultOrder(),
                'data' => $this->data($widget, $user),
            ];
        }

        usort($widgets, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $widgets;
    }

    /**
     * The widget catalogue, for the "customise" panel: metadata only, no data.
     *
     * @return list<array<string, mixed>>
     */
    public function available(User $user): array
    {
        return array_map(
            static fn (Widget $widget): array => $widget->toArray(),
            $this->registry->for($user),
        );
    }

    /**
     * The user's saved arrangement, keyed by widget key.
     *
     * Unknown keys are dropped here rather than at write time so a layout saved
     * before a widget was removed does not resurrect it.
     *
     * @return array<string, array{size: string, order: int}>
     */
    public function layout(User $user): array
    {
        $stored = $this->settings->getFrom(SettingsRepository::SCOPE_USER, $user->id, self::LAYOUT_KEY, []);

        if (! is_array($stored)) {
            return [];
        }

        $layout = [];
        $order = 0;

        foreach ($stored as $entry) {
            if (! is_array($entry) || ! isset($entry['key']) || ! is_string($entry['key'])) {
                continue;
            }

            if (! $this->registry->has($entry['key'])) {
                continue;
            }

            $size = isset($entry['size']) && is_string($entry['size'])
                ? WidgetSize::tryFrom($entry['size'])
                : null;

            $layout[$entry['key']] = [
                'size' => ($size ?? $this->registry->get($entry['key'])?->defaultSize() ?? WidgetSize::Medium)->value,
                'order' => $order += 10,
            ];
        }

        return $layout;
    }

    /**
     * Persist an ordered layout for a user and drop their cached dashboard.
     *
     * @param  list<array{key: string, size: string}>  $layout
     */
    public function saveLayout(User $user, array $layout): void
    {
        $clean = [];

        foreach ($layout as $entry) {
            if (! $this->registry->has($entry['key'])) {
                continue;
            }

            $size = WidgetSize::tryFrom($entry['size'])
                ?? $this->registry->get($entry['key'])?->defaultSize()
                ?? WidgetSize::Medium;

            $clean[] = ['key' => $entry['key'], 'size' => $size->value];
        }

        $this->settings->set(self::LAYOUT_KEY, $clean, SettingsRepository::SCOPE_USER, $user->id);

        $this->flush($user);
    }

    /**
     * Drop every cached widget payload for this user in the active workspace.
     */
    public function flush(User $user): void
    {
        foreach ($this->registry->keys() as $key) {
            $this->cache->forget($this->cacheKey($key, $user));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(Widget $widget, User $user): array
    {
        $ttl = $widget->cacheTtl();

        if ($ttl <= 0) {
            return $widget->data();
        }

        /** @var array<string, mixed> $data */
        $data = $this->cache->remember(
            $this->cacheKey($widget->key(), $user),
            $ttl,
            static fn (): array => $widget->data(),
        );

        return $data;
    }

    protected function cacheKey(string $widget, User $user): string
    {
        return sprintf('dashboard:%s:%d:%s', $this->tenant->id() ?? 'none', $user->id, $widget);
    }
}
