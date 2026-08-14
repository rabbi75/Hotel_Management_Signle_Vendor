<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Resources;

use App\Modules\Folio\Models\GuestFolio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuestFolio */
class GuestFolioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var GuestFolio $folio */
        $folio = $this->resource;

        return [
            'id' => $folio->id,
            'number' => $folio->number,
            'hotel_id' => $folio->hotel_id,
            'hotel' => $folio->relationLoaded('hotel') ? $folio->hotel?->name : null,
            'guest_id' => $folio->guest_id,
            'guest' => $folio->relationLoaded('guest') ? $folio->guest?->fullName() : null,
            'reservation_id' => $folio->reservation_id,
            'reservation' => $folio->relationLoaded('reservation') ? $folio->reservation?->number : null,
            'status' => $folio->status->value,
            'status_label' => $folio->status->label(),
            'status_color' => $folio->status->color(),
            'currency' => $folio->currency,
            'subtotal' => $folio->subtotal,
            'tax' => $folio->tax,
            'discount' => $folio->discount,
            'total' => $folio->total,
            'paid_amount' => $folio->paid_amount,
            'balance' => $folio->balance,
            'opened_at' => $folio->opened_at?->toIso8601String(),
            'closed_at' => $folio->closed_at?->toIso8601String(),
            'notes' => $folio->notes,
            'is_open' => $folio->isOpen(),
            'items' => $folio->relationLoaded('items')
                ? FolioItemResource::collection($folio->items)->resolve($request)
                : [],
            'payments' => $folio->relationLoaded('payments')
                ? GuestPaymentResource::collection($folio->payments)->resolve($request)
                : [],
            'invoice_id' => $folio->relationLoaded('invoice') ? $folio->invoice?->id : null,
            'invoice_number' => $folio->relationLoaded('invoice') ? $folio->invoice?->number : null,
            'created_at' => $folio->created_at?->toIso8601String(),
        ];
    }
}
