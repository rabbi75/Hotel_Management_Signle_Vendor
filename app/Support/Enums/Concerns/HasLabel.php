<?php

declare(strict_types=1);

namespace App\Support\Enums\Concerns;

/**
 * Shared presentation helpers for backed enums surfaced in the UI.
 *
 * Cases expose a human label and a badge colour token so a status never has to
 * be re-described in a React component.
 */
trait HasLabel
{
    /**
     * @return list<array{value: string|int, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
            ],
            self::cases(),
        );
    }

    /**
     * @return list<string|int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }

    public function color(): string
    {
        return 'neutral';
    }

    public function is(self $other): bool
    {
        return $this === $other;
    }

    public function isNot(self $other): bool
    {
        return $this !== $other;
    }
}
