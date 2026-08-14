<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum BedStatus: string
{
    use HasLabel;

    case Available = 'available';
    case Reserved = 'reserved';
    case Occupied = 'occupied';
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Reserved => 'info',
            self::Occupied => 'warning',
            self::Maintenance, self::OutOfService => 'destructive',
        };
    }

    public function isBookable(): bool
    {
        return $this === self::Available;
    }
}
