<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\TransactionStatus;
use App\Modules\Billing\Enums\TransactionType;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Billing\Support\Money;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Return money for a settled charge, in full or in part.
 *
 * The refund is written as its own `Transaction` rather than by mutating the
 * original: the ledger is append-only, and "this charge was later refunded" is
 * a different fact from "this charge never succeeded". Revenue figures subtract
 * refunds by reading those rows, so a partial refund needs no special case.
 */
class RefundTransaction
{
    public function __construct(
        protected GatewayManager $gateways,
        protected SecurityLogger $security,
    ) {}

    /**
     * @param  int|null  $amount  Minor units; null refunds the whole charge.
     */
    public function handle(Transaction $transaction, ?int $amount = null, ?string $reason = null): Transaction
    {
        $refundable = $this->guard($transaction, $amount);

        // Resolved before the gateway is called, never left as null: "refund it
        // all" means the part still outstanding, and a charge that was already
        // partly refunded would otherwise be reversed twice over.
        $amount ??= $refundable;

        // The gateway that took the money, not the configured default: an
        // installation that has since switched processors must still be able to
        // refund what the old one captured.
        $remote = $this->gateways->driver($transaction->gateway)->refund($transaction, $amount);

        if (! $remote->succeeded) {
            throw new BillingException(
                $remote->failureReason ?? __('The payment processor refused the refund.'),
            );
        }

        return DB::transaction(function () use ($transaction, $remote, $reason): Transaction {
            $refund = new Transaction([
                'invoice_id' => $transaction->invoice_id,
                'gateway' => $remote->gateway,
                'gateway_id' => $remote->gatewayId,
                'type' => TransactionType::Refund,
                'status' => TransactionStatus::Succeeded,
                'amount' => $remote->amount,
                'currency' => $remote->currency,
                'processed_at' => $remote->processedAt ?? CarbonImmutable::now(),
                'meta' => ['refunds' => $transaction->id, 'reason' => $reason],
            ]);
            $refund->company_id = $transaction->company_id;
            $refund->save();

            $this->settleInvoice($transaction);

            $this->security->log(
                SecurityEvent::PaymentRefunded,
                Auth::user(),
                __('Refunded :amount.', ['amount' => Money::of($remote->amount, $remote->currency)->format()]),
                [
                    'company_id' => $transaction->company_id,
                    'transaction_id' => $transaction->id,
                    'refund_id' => $refund->id,
                    'partial' => $remote->amount < $transaction->amount,
                    'reason' => $reason,
                ],
            );

            return $refund;
        });
    }

    /**
     * An invoice counts as refunded only once nothing is left of it. A partial
     * refund leaves it paid — the customer did pay, and some of it was returned.
     */
    protected function settleInvoice(Transaction $transaction): void
    {
        $invoice = Invoice::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->whereKey($transaction->invoice_id)
            ->first();

        if (! $invoice instanceof Invoice) {
            return;
        }

        $refunded = (int) Transaction::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('invoice_id', $invoice->id)
            ->where('type', TransactionType::Refund->value)
            ->where('status', TransactionStatus::Succeeded->value)
            ->sum('amount');

        if ($refunded >= $invoice->total) {
            $invoice->status = InvoiceStatus::Refunded;
            $invoice->save();
        }
    }

    /**
     * Reject anything that cannot be refunded, and report how much still can be.
     */
    protected function guard(Transaction $transaction, ?int $amount): int
    {
        if ($transaction->type !== TransactionType::Charge) {
            throw new BillingException(__('Only a charge can be refunded.'));
        }

        if ($transaction->status !== TransactionStatus::Succeeded) {
            throw new BillingException(__('Only a settled charge can be refunded.'));
        }

        if ($amount !== null && $amount <= 0) {
            throw new BillingException(__('A refund must be for a positive amount.'));
        }

        $alreadyRefunded = (int) Transaction::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('invoice_id', $transaction->invoice_id)
            ->where('type', TransactionType::Refund->value)
            ->where('status', TransactionStatus::Succeeded->value)
            ->sum('amount');

        $refundable = $transaction->amount - $alreadyRefunded;

        if ($refundable <= 0) {
            throw new BillingException(__('This charge has already been fully refunded.'));
        }

        if (($amount ?? $refundable) > $refundable) {
            throw new BillingException(__('That is more than the :amount still refundable.', [
                'amount' => Money::of($refundable, $transaction->currency)->format(),
            ]));
        }

        return $refundable;
    }
}
