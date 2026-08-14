<?php

declare(strict_types=1);

namespace App\Modules\Search\DTOs;

use App\Support\DTOs\Data;

/**
 * One navigable page discovered in the navigation tree.
 */
readonly class PageResult extends Data
{
    public function __construct(
        public string $label,
        public string $href,
        public ?string $icon,
        public string $section,
    ) {}
}
