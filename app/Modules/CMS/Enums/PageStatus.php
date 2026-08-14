<?php

declare(strict_types=1);

namespace App\Modules\CMS\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum PageStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Published => 'success',
            self::Scheduled => 'warning',
        };
    }
}
