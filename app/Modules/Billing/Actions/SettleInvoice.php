<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Enums\TransactionStatus;
use App\Modules\Billing\Enums\TransactionType;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Record payment of an open invoice and lift the workspace out of dunning.
 *
 * This settles the kit's own ledger; it does not ask a processor to capture
 * anything. That is the honest shape for the operator action it serves — a
 * customer who paid by transfer, or whose card went through out of band — and
 * it is the only shape available today, because {@see PaymentGateway}
 * has no charge method. Adding one is a separate change to that interface.
 */
class SettleInvoice
{
    public function __construct(protected SecurityLogger $security) {}

    public function handle(Invoice $invoice, ?string $reference = null): Transaction
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new BillingException(__('This invoice is already paid.'));
        }

        if ($invoice->status->isSettled()) {
            throw new BillingException(__('A settled invoice cannot be paid again.'));
        }

        return DB::transaction(function () use ($invoice, $reference): Transaction {
            $now = CarbonImmutable::now();

            $payment = new Transaction([
                'invoice_id' => $invoice->id,
                'gateway' => $invoice->gateway ?? (string) config('saas.billing.gateway', 'manual'),
                'gateway_id' => $reference,
                'type' => TransactionType::Charge,
                'status' => TransactionStatus::Succeeded,
                'amount' => $invoice->total,
                'currency' => $invoice->currency,
                'processed_at' => $now,
                'meta' => ['recorded_by_operator' => true, 'reference' => $reference],
            ]);
            $payment->company_id = $invoice->company_id;
            $payment->save();

            $invoice->status = InvoiceStatus::Paid;
            $invoice->paid_at = $now;
            $invoice->save();

            $this->clearDunning($invoice);

            $this->security->log(
                SecurityEvent::PaymentRetried,
                Auth::user(),
                __('Recorded payment of invoice :number.', ['number' => $invoice->number]),
                [
                    'company_id' => $invoice->company_id,
                    'invoice_id' => $invoice->id,
                    'reference' => $reference,
                ],
            );

            return $payment;
        });
    }

    /**
     * A workspace stops being past-due only once it owes nothing. Clearing on
     * the first payment would restore access to someone with three unpaid
     * invoices behind them.
     */
    protected function clearDunning(Invoice $invoice): void
    {
        $subscription = Subscription::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->whereKey($invoice->subscription_id)
            ->first();

        if (! $subscription instanceof Subscription || $subscription->status !== SubscriptionStatus::PastDue) {
            return;
        }

        $stillOwing = Invoice::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('subscription_id', $subscription->id)
            ->where('status', InvoiceStatus::Open->value)
            ->exists();

        if ($stillOwing) {
            return;
        }

        $subscription->status = SubscriptionStatus::Active;
        $subscription->past_due_since = null;
        $subscription->dunning_attempts = 0;
        $subscription->save();
    }
}
