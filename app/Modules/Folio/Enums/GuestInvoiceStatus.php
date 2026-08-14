<?php

declare(strict_types=1);

namespace App\Modules\Folio\Enums;

enum GuestInvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Issued => __('Issued'),
            self::Paid => __('Paid'),
            self::Void => __('Void'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'blue',
            self::Paid => 'green',
            self::Void => 'gray',
        };
    }

    /** @return list<array{value: string, label: string, color: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
            ],
            self::cases(),
        );
    }
}
