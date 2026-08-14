<?php

declare(strict_types=1);

namespace App\Modules\Folio\Enums;

enum FolioStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Closed => __('Closed'),
            self::Void => __('Void'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Closed => 'green',
            self::Void => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
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
