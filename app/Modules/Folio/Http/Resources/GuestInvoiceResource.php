<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Resources;

use App\Modules\Folio\Models\GuestInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuestInvoice */
class GuestInvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var GuestInvoice $invoice */
        $invoice = $this->resource;

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'guest_id' => $invoice->guest_id,
            'guest' => $invoice->relationLoaded('guest') ? $invoice->guest?->fullName() : null,
            'reservation_id' => $invoice->reservation_id,
            'reservation' => $invoice->relationLoaded('reservation') ? $invoice->reservation?->number : null,
            'guest_folio_id' => $invoice->guest_folio_id,
            'folio' => $invoice->relationLoaded('folio') ? $invoice->folio?->number : null,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'status_color' => $invoice->status->color(),
            'subtotal' => $invoice->subtotal,
            'tax' => $invoice->tax,
            'discount' => $invoice->discount,
            'total' => $invoice->total,
            'currency' => $invoice->currency,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'created_at' => $invoice->created_at?->toIso8601String(),
        ];
    }
}
