<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Actions\RefundTransaction;
use App\Modules\Billing\Actions\SettleInvoice;
use App\Modules\Billing\Console\RenewSubscriptionsCommand;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Enums\TransactionStatus;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Billing\Support\Money;
use App\Modules\Platform\Http\Controllers\Billing\Concerns\ManagesPlatformBilling;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The collections desk.
 *
 * Everything failing to be paid, in one queue, with the two actions that
 * actually resolve it: record the payment, or buy the customer more time. The
 * countdown shown against each row is real — {@see RenewSubscriptionsCommand}
 * revokes access once `past_due_since` is older than `saas.billing.grace_days`.
 */
class PlatformDunningController extends Controller
{
    use ManagesPlatformBilling;

    public function __construct(
        protected SettleInvoice $settle,
        protected RefundTransaction $refund,
        protected SecurityLogger $security,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeBillingRead($request);

        $graceDays = (int) config('saas.billing.grace_days', 3);
        $now = CarbonImmutable::now();

        $pastDue = $this->subscriptions()
            ->where('status', SubscriptionStatus::PastDue->value)
            ->with(['company', 'plan'])
            ->orderBy('past_due_since')
            ->get()
            ->map(fn (Subscription $subscription): array => [
                'id' => $subscription->id,
                'company' => $subscription->company->name,
                'company_uuid' => $subscription->company->uuid,
                'plan' => $subscription->plan->name,
                'mrr' => $subscription->price()->toArray(),
                'past_due_since' => $subscription->past_due_since?->toIso8601String(),
                'dunning_attempts' => $subscription->dunning_attempts,

                // Negative means the grace window has already lapsed and the
                // next scheduler run will revoke access.
                'days_left' => $subscription->past_due_since === null
                    ? null
                    : $graceDays - (int) $subscription->past_due_since->diffInDays($now),
            ])
            ->values()
            ->all();

        $overdue = $this->invoices()
            ->where('status', InvoiceStatus::Open->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->with('company')
            ->orderBy('due_at')
            ->limit(100)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'company' => $invoice->company->name,
                'total' => $invoice->totalMoney()->toArray(),
                'due_at' => $invoice->due_at?->toIso8601String(),
                'days_overdue' => (int) $invoice->due_at?->diffInDays($now),
            ])
            ->values()
            ->all();

        $failed = $this->transactions()
            ->where('status', TransactionStatus::Failed->value)
            ->with(['invoice.company'])
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'company' => $transaction->invoice?->company->name ?? '',
                'invoice' => $transaction->invoice?->number,
                'amount' => $transaction->amountMoney()->toArray(),
                'reason' => $transaction->failure_reason,
                'failed_at' => $transaction->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/billing/dunning', [
            'pastDue' => $pastDue,
            'overdue' => $overdue,
            'failed' => $failed,
            'graceDays' => $graceDays,
            'exposure' => Money::of(array_sum(array_map(
                static fn (array $row): int => (int) $row['total']['amount'],
                $overdue,
            )))->toArray(),
            'can' => [
                'manage' => $request->user('admin')?->can('platform.billing.manage') ?? false,
            ],
        ]);
    }

    /**
     * Record that an open invoice was paid.
     */
    public function settle(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeBillingWrite($request);

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->settle->handle($invoice, $validated['reference'] ?? null);
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Invoice :number marked as paid.', ['number' => $invoice->number]));
    }

    /**
     * Push the grace deadline out, so a customer mid-conversation is not cut
     * off by the next scheduler run.
     */
    public function extendGrace(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->authorizeBillingWrite($request);

        $validated = $request->validate([
            'days' => ['required', 'integer', 'between:1,60'],
        ]);

        abort_unless($subscription->status === SubscriptionStatus::PastDue, 422);

        $days = (int) $validated['days'];

        // Moving the marker forward is what extends the window: the command
        // compares `past_due_since` against a fixed grace period, so there is
        // no separate deadline column to write.
        $subscription->past_due_since = ($subscription->past_due_since ?? CarbonImmutable::now())->addDays($days);
        $subscription->save();

        $this->security->log(
            SecurityEvent::GraceExtended,
            null,
            __('Extended the grace period by :days day(s).', ['days' => $days]),
            ['company_id' => $subscription->company_id, 'subscription_id' => $subscription->id, 'days' => $days],
        );

        return back()->with('success', __('Grace period extended by :days day(s).', ['days' => $days]));
    }

    /**
     * Refund a settled charge, in full or in part.
     */
    public function refund(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeBillingWrite($request);

        $validated = $request->validate([
            'amount' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $refund = $this->refund->handle(
                $transaction,
                isset($validated['amount']) ? (int) $validated['amount'] : null,
                $validated['reason'] ?? null,
            );
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Refunded :amount.', [
            'amount' => $refund->amountMoney()->format(),
        ]));
    }
}
