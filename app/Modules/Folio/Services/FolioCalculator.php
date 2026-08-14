<?php

declare(strict_types=1);

namespace App\Modules\Folio\Services;

use App\Modules\Folio\Enums\FolioItemType;
use App\Modules\Folio\Enums\GuestPaymentStatus;
use App\Modules\Folio\Models\GuestFolio;

class FolioCalculator
{
    public function recalculate(GuestFolio $folio): GuestFolio
    {
        $folio->loadMissing(['items', 'payments']);

        $subtotal = 0;
        $tax = 0;
        $discount = 0;

        foreach ($folio->items as $item) {
            match ($item->type) {
                FolioItemType::Tax => $tax += $item->amount,
                FolioItemType::Discount => $discount += abs($item->amount),
                default => $subtotal += $item->amount,
            };

            if ($item->type !== FolioItemType::Tax && $item->tax_amount > 0) {
                $tax += $item->tax_amount;
            }
        }

        $total = max(0, $subtotal + $tax - $discount);

        $paid = $folio->payments
            ->filter(static fn ($payment): bool => $payment->status->countsTowardPaid())
            ->sum('amount');

        $folio->subtotal = $subtotal;
        $folio->tax = $tax;
        $folio->discount = $discount;
        $folio->total = $total;
        $folio->paid_amount = (int) $paid;
        $folio->balance = max(0, $total - $folio->paid_amount);
        $folio->save();

        return $folio->fresh(['items', 'payments']) ?? $folio;
    }
}
