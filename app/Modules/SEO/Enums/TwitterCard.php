<?php

declare(strict_types=1);

namespace App\Modules\SEO\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * The Twitter/X card layouts a page may declare.
 */
enum TwitterCard: string
{
    use HasLabel;

    case Summary = 'summary';
    case SummaryLargeImage = 'summary_large_image';
    case App = 'app';
    case Player = 'player';
}
