<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum HousekeepingTaskType: string
{
    use HasLabel;

    case Checkout = 'checkout';
    case Stayover = 'stayover';
    case DeepClean = 'deep_clean';
    case Inspection = 'inspection';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Checkout => __('Checkout clean'),
            self::Stayover => __('Stayover service'),
            self::DeepClean => __('Deep clean'),
            self::Inspection => __('Inspection'),
            self::Other => __('Other'),
        };
    }
}
