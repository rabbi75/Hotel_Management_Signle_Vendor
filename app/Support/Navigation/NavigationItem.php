<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * A single sidebar link, or a parent with children.
 */
class NavigationItem
{
    /** @var list<self> */
    protected array $children = [];

    /** @var list<string> */
    protected array $permissions = [];

    /**
     * A plan entitlement key that must be granted for this item to render, on
     * top of the permission check. Null means the item is not plan-gated.
     */
    protected ?string $feature = null;

    protected ?string $icon = null;

    protected ?string $badge = null;

    protected int $order = 0;

    /**
     * Route name patterns that should mark this item active, in addition to
     * an exact match on the item's own route.
     *
     * @var list<string>
     */
    protected array $activeWhen = [];

    final public function __construct(
        public readonly string $label,
        public readonly ?string $route = null,
    ) {}

    public static function make(string $label, ?string $route = null): static
    {
        return new static($label, $route);
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * The item renders only if the user holds at least one of these.
     */
    public function permissions(string ...$permissions): static
    {
        $this->permissions = array_values($permissions);

        return $this;
    }

    /**
     * @param  list<self>  $children
     */
    public function children(array $children): static
    {
        $this->children = $children;

        return $this;
    }

    public function activeWhen(string ...$patterns): static
    {
        $this->activeWhen = array_values($patterns);

        return $this;
    }

    /**
     * Hide this item unless the active workspace's plan grants $feature. The key
     * must be declared in config/entitlements.php.
     */
    public function feature(string $feature): static
    {
        $this->feature = $feature;

        return $this;
    }

    public function badge(string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function order(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->feature !== null && ! app(SubscriptionLimits::class)->hasFeature($this->feature)) {
            return false;
        }

        if ($this->permissions === []) {
            return true;
        }

        return $user->canAny($this->permissions);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $user): array
    {
        $children = array_values(array_filter(
            $this->children,
            static fn (self $child): bool => $child->isVisibleTo($user),
        ));

        return [
            'label' => __($this->label),
            'route' => $this->route,

            // Relative, not absolute: this tree is cached per user for
            // saas.cache.navigation_ttl, and an absolute URL would bake the
            // request's host into that cache — wrong the moment the app is
            // reached over a second domain, a different port, or behind a proxy.
            'href' => $this->route !== null && Route::has($this->route)
                ? route($this->route, [], false)
                : null,
            'icon' => $this->icon,
            'badge' => $this->badge,
            'activeWhen' => $this->activeWhen !== []
                ? $this->activeWhen
                : ($this->route !== null ? [$this->route] : []),
            'children' => array_map(static fn (self $child): array => $child->toArray($user), $children),
        ];
    }
}
