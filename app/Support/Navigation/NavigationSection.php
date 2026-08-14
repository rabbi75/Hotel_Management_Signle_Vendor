<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Modules\User\Models\User;

/**
 * A titled group of sidebar items.
 */
class NavigationSection
{
    /** @var list<NavigationItem> */
    protected array $items = [];

    final public function __construct(
        public readonly string $label,
        public readonly int $order = 0,
    ) {}

    public static function make(string $label, int $order = 0): static
    {
        return new static($label, $order);
    }

    /**
     * @param  list<NavigationItem>  $items
     */
    public function items(array $items): static
    {
        $this->items = $items;

        return $this;
    }

    /**
     * Fold another section carrying the same label into this one.
     *
     * Several modules contribute to a shared heading ("Content", "Administration"),
     * so their items belong in one group rather than in repeated headings. The
     * lower order wins so the merged section keeps the earliest position claimed
     * for that label; items are re-sorted by their own order in {@see toArray()}.
     */
    public function merge(self $other): static
    {
        return static::make($this->label, min($this->order, $other->order))
            ->items([...$this->items, ...$other->items]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $user): array
    {
        $items = array_values(array_filter(
            $this->items,
            static fn (NavigationItem $item): bool => $item->isVisibleTo($user),
        ));

        usort($items, static fn (NavigationItem $a, NavigationItem $b): int => $a->getOrder() <=> $b->getOrder());

        return [
            'label' => __($this->label),
            'items' => array_map(static fn (NavigationItem $item): array => $item->toArray($user), $items),
        ];
    }
}
