<?php

declare(strict_types=1);

namespace App\Modules\Folio\Enums;

enum GuestPaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case MobileWallet = 'mobile_wallet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
            self::BankTransfer => __('Bank transfer'),
            self::MobileWallet => __('Mobile wallet'),
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
