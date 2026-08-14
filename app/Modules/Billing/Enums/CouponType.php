<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum CouponType: string
{
    use HasLabel;

    /** `value` is a whole percentage, 1–100. */
    case Percent = 'percent';

    /** `value` is an amount in integer minor units. */
    case Fixed = 'fixed';

    public function color(): string
    {
        return $this === self::Percent ? 'info' : 'primary';
    }
}
