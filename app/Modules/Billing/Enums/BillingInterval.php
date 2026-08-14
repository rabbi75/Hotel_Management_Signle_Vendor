<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

use App\Support\Enums\Concerns\HasLabel;
use Carbon\CarbonImmutable;
use DateTimeInterface;

enum BillingInterval: string
{
    use HasLabel;

    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }

    public function advance(DateTimeInterface $from): CarbonImmutable
    {
        return CarbonImmutable::instance($from)->addMonthsNoOverflow($this->months());
    }

    public function color(): string
    {
        return $this === self::Yearly ? 'primary' : 'neutral';
    }
}
