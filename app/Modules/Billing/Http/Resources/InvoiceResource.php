<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->resource;

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'status_color' => $invoice->status->color(),
            'subtotal' => $invoice->subtotal,
            'tax' => $invoice->tax,
            'discount' => $invoice->discount,
            'total' => $invoice->total,
            'currency' => $invoice->currency,
            'subtotal_formatted' => $invoice->subtotalMoney()->format(),
            'tax_formatted' => $invoice->taxMoney()->format(),
            'discount_formatted' => $invoice->discountMoney()->format(),
            'total_formatted' => $invoice->totalMoney()->format(),
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'plan' => $invoice->relationLoaded('subscription')
                ? $invoice->subscription?->plan->name
                : null,

            // Only meaningful in the console, where the listing spans tenants.
            // A tenant never loads the relation, so it stays null there.
            'company' => $invoice->relationLoaded('company')
                ? $invoice->company?->name
                : null,
            'company_uuid' => $invoice->relationLoaded('company')
                ? $invoice->company?->uuid
                : null,
            'lines' => $invoice->relationLoaded('lines')
                ? $invoice->lines->map(fn (InvoiceLine $line): array => [
                    'id' => $line->id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_amount' => $line->unit_amount,
                    'unit_amount_formatted' => Money::of($line->unit_amount, $invoice->currency)->format(),
                    'amount' => $line->amount,
                    'amount_formatted' => Money::of($line->amount, $invoice->currency)->format(),
                    'period' => $line->period,
                ])->values()->all()
                : [],
            'created_at' => $invoice->created_at?->toIso8601String(),
        ];
    }
}
