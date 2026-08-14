<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Money;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns a subscription period into an invoice with its lines.
 *
 * Totals are recomputed from the lines rather than trusted from the caller, so
 * a line and its invoice can never disagree.
 */
class GenerateInvoice
{
    /**
     * @param  list<array{description: string, quantity?: int, unit_amount: int, period?: string|null}>  $extraLines
     */
    public function handle(
        Subscription $subscription,
        ?Coupon $coupon = null,
        InvoiceStatus $status = InvoiceStatus::Open,
        array $extraLines = [],
    ): Invoice {
        $subscription->loadMissing('plan');
        $plan = $subscription->plan;
        $currency = $plan->currency;

        $periodLabel = $this->periodLabel($subscription);

        $lines = [
            [
                'description' => __(':plan (:interval)', [
                    'plan' => $plan->name,
                    'interval' => $subscription->interval->label(),
                ]),
                'quantity' => $subscription->quantity,
                'unit_amount' => $plan->priceFor($subscription->interval)->amount,
                'period' => $periodLabel,
            ],
            ...$extraLines,
        ];

        $subtotal = Money::zero($currency);

        foreach ($lines as $line) {
            $subtotal = $subtotal->plus(
                Money::of($line['unit_amount'], $currency)->multipliedBy($line['quantity'] ?? 1),
            );
        }

        $discount = $coupon instanceof Coupon
            ? $coupon->discountFor($subtotal)
            : Money::zero($currency);

        $total = $subtotal->minus($discount)->atLeastZero();

        return DB::transaction(function () use ($subscription, $lines, $subtotal, $discount, $total, $currency, $status, $periodLabel): Invoice {
            $invoice = new Invoice([
                'subscription_id' => $subscription->id,
                'number' => $this->nextNumber(),
                'status' => $status,
                'subtotal' => $subtotal->amount,
                'tax' => 0,
                'discount' => $discount->amount,
                'total' => $total->amount,
                'currency' => $currency,
                'issued_at' => CarbonImmutable::now(),
                'due_at' => CarbonImmutable::now()->addDays(7),
                'paid_at' => $status === InvoiceStatus::Paid ? CarbonImmutable::now() : null,
                'gateway' => $subscription->gateway,
                'gateway_id' => null,
                'notes' => null,
            ]);

            $invoice->company_id = $subscription->company_id;
            $invoice->save();

            foreach ($lines as $line) {
                $quantity = $line['quantity'] ?? 1;

                InvoiceLine::query()->create([
                    'invoice_id' => $invoice->id,
                    'description' => $line['description'],
                    'quantity' => $quantity,
                    'unit_amount' => $line['unit_amount'],
                    'amount' => $line['unit_amount'] * $quantity,
                    'period' => $line['period'] ?? $periodLabel,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * Sequential per installation, prefixed from configuration.
     *
     * The probe is deliberately unscoped: invoice numbers must be unique across
     * the whole installation, not merely inside one workspace.
     */
    public function nextNumber(): string
    {
        $prefix = (string) config('saas.billing.invoice.prefix', 'INV-');

        $count = Invoice::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->count();

        do {
            $count++;
            $number = $prefix.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
        } while (Invoice::query()->withoutGlobalScope(CompanyScope::class)->where('number', $number)->exists());

        return $number;
    }

    protected function periodLabel(Subscription $subscription): ?string
    {
        $start = $subscription->current_period_start;
        $end = $subscription->current_period_end;

        if ($start === null || $end === null) {
            return null;
        }

        return $start->toDateString().' – '.$end->toDateString();
    }
}
