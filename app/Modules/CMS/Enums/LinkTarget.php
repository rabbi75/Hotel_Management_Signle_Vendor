<?php

declare(strict_types=1);

namespace App\Modules\CMS\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum LinkTarget: string
{
    use HasLabel;

    case Self = '_self';
    case Blank = '_blank';

    public function label(): string
    {
        return match ($this) {
            self::Self => __('Same tab'),
            self::Blank => __('New tab'),
        };
    }
}
