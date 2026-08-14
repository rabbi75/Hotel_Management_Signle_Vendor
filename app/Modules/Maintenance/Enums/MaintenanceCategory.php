<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum MaintenanceCategory: string
{
    use HasLabel;

    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case Hvac = 'hvac';
    case Furniture = 'furniture';
    case Appliance = 'appliance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Plumbing => __('Plumbing'),
            self::Electrical => __('Electrical'),
            self::Hvac => __('HVAC'),
            self::Furniture => __('Furniture'),
            self::Appliance => __('Appliance'),
            self::Other => __('Other'),
        };
    }
}
