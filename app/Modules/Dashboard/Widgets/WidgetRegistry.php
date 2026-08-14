<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Widgets;

use App\Modules\User\Models\User;

/**
 * The set of widgets available to the dashboard.
 *
 * Registered as a container singleton so any module can contribute a widget
 * from its own service provider without the Dashboard module having to know
 * that module exists.
 */
class WidgetRegistry
{
    /** @var array<string, Widget> */
    protected array $widgets = [];

    public function register(Widget $widget): void
    {
        $this->widgets[$widget->key()] = $widget;
    }

    /**
     * @param  list<Widget>  $widgets
     */
    public function registerMany(array $widgets): void
    {
        foreach ($widgets as $widget) {
            $this->register($widget);
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->widgets);
    }

    public function get(string $key): ?Widget
    {
        return $this->widgets[$key] ?? null;
    }

    /**
     * @return array<string, Widget>
     */
    public function all(): array
    {
        return $this->widgets;
    }

    /**
     * The widgets a user is permitted to see, in their declared default order.
     *
     * @return list<Widget>
     */
    public function for(User $user): array
    {
        $visible = array_values(array_filter(
            $this->widgets,
            static fn (Widget $widget): bool => $widget->isVisibleTo($user),
        ));

        usort($visible, static fn (Widget $a, Widget $b): int => $a->defaultOrder() <=> $b->defaultOrder());

        return $visible;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->widgets);
    }
}
