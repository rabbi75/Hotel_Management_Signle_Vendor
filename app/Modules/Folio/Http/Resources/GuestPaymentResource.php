<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Resources;

use App\Modules\Folio\Models\GuestPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuestPayment */
class GuestPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var GuestPayment $payment */
        $payment = $this->resource;

        return [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'method' => $payment->method->value,
            'method_label' => $payment->method->label(),
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }
}
