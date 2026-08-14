<?php

declare(strict_types=1);

namespace App\Modules\Folio\Enums;

enum FolioItemType: string
{
    case Room = 'room';
    case Service = 'service';
    case Tax = 'tax';
    case Discount = 'discount';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Room => __('Room charge'),
            self::Service => __('Service'),
            self::Tax => __('Tax'),
            self::Discount => __('Discount'),
            self::Adjustment => __('Adjustment'),
        };
    }
}
