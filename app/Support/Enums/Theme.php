<?php

declare(strict_types=1);

namespace App\Support\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * Colour scheme preference. Resolved server-side and inlined into the document
 * so the first paint already matches the user's choice.
 */
enum Theme: string
{
    use HasLabel;

    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';
}
