<?php

declare(strict_types=1);

namespace App\Modules\Folio\Enums;

enum HotelServiceCategory: string
{
    case Room = 'room';
    case Food = 'food';
    case Spa = 'spa';
    case Laundry = 'laundry';
    case Transport = 'transport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Room => __('Room'),
            self::Food => __('Food & beverage'),
            self::Spa => __('Spa & wellness'),
            self::Laundry => __('Laundry'),
            self::Transport => __('Transport'),
            self::Other => __('Other'),
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
