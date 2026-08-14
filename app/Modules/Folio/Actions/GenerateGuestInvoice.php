<?php

declare(strict_types=1);

namespace App\Modules\Folio\Actions;

use App\Modules\Folio\Enums\GuestInvoiceStatus;
use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Folio\Models\GuestInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateGuestInvoice
{
    public function handle(GuestFolio $folio): GuestInvoice
    {
        return DB::transaction(function () use ($folio): GuestInvoice {
            $existing = GuestInvoice::query()
                ->where('guest_folio_id', $folio->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof GuestInvoice) {
                return $existing->load('lines');
            }

            $folio->loadMissing(['items', 'guest', 'reservation']);

            $invoice = new GuestInvoice([
                'guest_folio_id' => $folio->id,
                'guest_id' => $folio->guest_id,
                'reservation_id' => $folio->reservation_id,
                'number' => static::nextNumber(),
                'status' => $folio->balance <= 0 && $folio->paid_amount >= $folio->total
                    ? GuestInvoiceStatus::Paid
                    : GuestInvoiceStatus::Issued,
                'subtotal' => $folio->subtotal,
                'tax' => $folio->tax,
                'discount' => $folio->discount,
                'total' => $folio->total,
                'currency' => $folio->currency,
                'issued_at' => CarbonImmutable::now(),
                'paid_at' => $folio->balance <= 0 ? CarbonImmutable::now() : null,
                'notes' => $folio->notes,
            ]);
            $invoice->save();

            foreach ($folio->items as $item) {
                $invoice->lines()->create([
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'amount' => $item->amount,
                ]);
            }

            return $invoice->fresh(['lines', 'guest', 'folio', 'reservation']) ?? $invoice;
        });
    }

    public static function nextNumber(): string
    {
        $prefix = 'GINV-'.now()->format('Ymd').'-';

        do {
            $number = $prefix.Str::upper(Str::random(4));
        } while (GuestInvoice::query()->where('number', $number)->exists());

        return $number;
    }
}
