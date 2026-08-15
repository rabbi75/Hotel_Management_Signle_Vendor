<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Enums;

use App\Support\Enums\Concerns\HasLabel;

enum PaymentMethodDriver: string
{
    use HasLabel;

    case CashOnDelivery = 'cash_on_delivery';
    case Stripe = 'stripe';
    case Paypal = 'paypal';
    case Sslcommerz = 'sslcommerz';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Aamarpay = 'aamarpay';
    case Rocket = 'rocket';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on delivery',
            self::Stripe => 'Stripe',
            self::Paypal => 'PayPal',
            self::Sslcommerz => 'SSLCommerz',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Aamarpay => 'aamarPay',
            self::Rocket => 'Rocket',
            self::BankTransfer => 'Bank transfer',
            self::Card => 'Visa / Mastercard',
            self::Cash => 'Cash at hotel',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Pay in cash when you arrive. Your stay request is placed immediately.',
            self::Stripe => 'Pay securely with a card through Stripe.',
            self::Paypal => 'Pay with your PayPal balance or linked card.',
            self::Sslcommerz => 'Pay with cards and local wallets through SSLCommerz.',
            self::Bkash => 'Send the stay total to the hotel bKash merchant number.',
            self::Nagad => 'Send the stay total to the hotel Nagad merchant number.',
            self::Aamarpay => 'Pay with cards and mobile banking through aamarPay.',
            self::Rocket => 'Send the stay total to the hotel Rocket merchant number.',
            self::BankTransfer => 'Transfer the stay total to the hotel bank account, then the desk will match it.',
            self::Card => 'Pay with Visa or Mastercard. Enter the transaction reference after the charge.',
            self::Cash => 'Pay cash at reception when you check in.',
        };
    }

    public function defaultInstructions(): string
    {
        return match ($this) {
            self::CashOnDelivery, self::Cash => 'Pay the quoted total at reception on arrival. No online payment is required.',
            self::BankTransfer => 'Transfer the quoted total to the hotel account and keep the bank reference. The front desk will match it to your booking.',
            self::Bkash => 'Send the quoted total as a Send Money / Payment to the hotel bKash number, then enter the TrxID below.',
            self::Nagad => 'Send the quoted total to the hotel Nagad number, then enter the transaction ID below.',
            self::Rocket => 'Send the quoted total to the hotel Rocket number, then enter the transaction ID below.',
            self::Stripe, self::Paypal, self::Sslcommerz, self::Aamarpay, self::Card => 'Complete the payment with this provider, then enter the transaction or receipt reference so we can place your order.',
        };
    }

    /**
     * Offline methods place the reservation immediately. Card and wallet
     * methods require the guest to finish payment before the order is created.
     */
    public function requiresPrepaid(): bool
    {
        return match ($this) {
            self::CashOnDelivery, self::Cash, self::BankTransfer => false,
            default => true,
        };
    }
}
