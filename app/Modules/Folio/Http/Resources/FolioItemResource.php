<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Resources;

use App\Modules\Folio\Models\FolioItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FolioItem */
class FolioItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var FolioItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'type' => $item->type->value,
            'type_label' => $item->type->label(),
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'amount' => $item->amount,
            'tax_amount' => $item->tax_amount,
            'hotel_service_id' => $item->hotel_service_id,
            'posted_at' => $item->posted_at?->toIso8601String(),
        ];
    }
}
