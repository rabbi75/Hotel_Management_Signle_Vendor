<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * How much of the dashboard grid a widget occupies.
 *
 * The values are the contract with the React grid, so they are an enum rather
 * than free-form strings a layout payload could smuggle anything into.
 */
enum WidgetSize: string
{
    use HasLabel;

    case Small = 'sm';
    case Medium = 'md';
    case Large = 'lg';
    case Full = 'full';

    /**
     * Columns spanned in the 12-column grid.
     */
    public function columns(): int
    {
        return match ($this) {
            self::Small => 3,
            self::Medium => 6,
            self::Large => 8,
            self::Full => 12,
        };
    }
}
