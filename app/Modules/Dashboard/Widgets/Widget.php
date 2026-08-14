<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Widgets;

use App\Modules\Dashboard\Enums\WidgetSize;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\User\Models\User;

/**
 * One panel on the dashboard.
 *
 * A widget owns its identity, its visibility rule and its payload; it knows
 * nothing about layout or caching, which belong to
 * {@see DashboardService}.
 */
abstract class Widget
{
    /**
     * Stable identifier. Persisted in the user's saved layout, so renaming one
     * silently drops it from every existing layout.
     */
    abstract public function key(): string;

    abstract public function title(): string;

    /**
     * The widget's payload, always scoped to the active workspace.
     *
     * @return array<string, mixed>
     */
    abstract public function data(): array;

    public function description(): ?string
    {
        return null;
    }

    /**
     * Permission required to see this widget, or null when everyone who can
     * reach the dashboard may see it.
     */
    public function permission(): ?string
    {
        return 'dashboard.view';
    }

    public function defaultSize(): WidgetSize
    {
        return WidgetSize::Medium;
    }

    /**
     * Where the widget sits before the user rearranges anything.
     */
    public function defaultOrder(): int
    {
        return 100;
    }

    /**
     * Seconds to cache {@see data()}. Zero disables caching for widgets whose
     * payload is cheap or must always be live.
     */
    public function cacheTtl(): int
    {
        return (int) config('saas.cache.dashboard_ttl');
    }

    public function isVisibleTo(User $user): bool
    {
        $permission = $this->permission();

        return $permission === null || $user->can($permission);
    }

    /**
     * The widget's metadata, without its payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'title' => $this->title(),
            'description' => $this->description(),
            'size' => $this->defaultSize()->value,
            'order' => $this->defaultOrder(),
        ];
    }
}
