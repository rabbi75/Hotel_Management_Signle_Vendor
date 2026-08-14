<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum HotelStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Inactive = 'inactive';

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'neutral',
        };
    }
}
