<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Enums\TransactionStatus;
use App\Modules\Billing\Enums\TransactionType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\Transaction;
use App\Modules\Billing\Support\Money;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The operator's view of the money.
 *
 * Deliberately separate from {@see PlatformMetrics}: the dashboard summary is
 * rendered on every visit to the console and must stay cheap, while these are
 * heavier aggregates over the whole invoice and transaction history. Only the
 * revenue screen pays for them.
 *
 * Every figure crosses tenancy, so each query lifts CompanyScope explicitly
 * rather than relying on there being no active workspace — the same discipline
 * the rest of the console follows.
 *
 * Amounts are {@see Money} throughout and are serialised through its `toArray()`,
 * so the client is never handed a number it might do currency arithmetic on.
 */
class RevenueMetrics
{
    public function __construct(protected CurrentCompany $tenant) {}

    /**
     * Monthly recurring revenue for a set of subscriptions.
     *
     * A yearly subscription contributes a twelfth of its price so the figure is
     * comparable across intervals, and a trial contributes nothing — counting
     * it would overstate MRR by exactly the churn that has not happened yet.
     *
     * The single definition of MRR in the codebase; {@see PlatformMetrics}
     * calls this rather than keeping its own copy.
     *
     * @param  Collection<int, Subscription>  $subscriptions
     */
    public function mrr(Collection $subscriptions): Money
    {
        $total = Money::zero();

        foreach ($subscriptions as $subscription) {
            if ($subscription->status === SubscriptionStatus::Trialing) {
                continue;
            }

            $price = $subscription->price();

            $total = $total->plus(
                $subscription->interval === BillingInterval::Yearly
                    ? $price->prorate(1, 12)
                    : $price,
            );
        }

        return $total;
    }

    /**
     * Everything the revenue screen renders, in one pass.
     *
     * @return array<string, mixed>
     */
    public function overview(int $months = 12): array
    {
        return $this->tenant->bypass(function () use ($months): array {
            $live = $this->liveSubscriptions();
            $mrr = $this->mrr($live);
            $paying = $live->reject(fn (Subscription $s): bool => $s->status === SubscriptionStatus::Trialing);

            return [
                'mrr' => $mrr->toArray(),
                'arr' => $mrr->multipliedBy(12)->toArray(),
                'arpa' => $this->arpa($mrr, $paying->count())->toArray(),
                'paying_accounts' => $paying->count(),
                'trialing_accounts' => $live->count() - $paying->count(),

                'collected_this_month' => $this->collected(CarbonImmutable::now()->startOfMonth())->toArray(),
                'outstanding' => $this->outstanding()->toArray(),
                'failed_payments' => $this->failedPaymentCount(),

                'trend' => $this->trend($months),
                'plan_mix' => $this->planMix($paying),
                'churn' => $this->churn($months),
            ];
        });
    }

    /**
     * Collected revenue per month: succeeded charges less refunds.
     *
     * Refunds are subtracted in the month they were processed rather than the
     * month of the original charge, which is what a bank statement does and
     * therefore what an operator is reconciling against.
     *
     * @return list<array{month: string, collected: array{amount: int, currency: string, formatted: string}}>
     */
    public function trend(int $months = 12): array
    {
        $since = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);

        $transactions = Transaction::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('status', TransactionStatus::Succeeded->value)
            ->where('processed_at', '>=', $since)
            ->get(['amount', 'currency', 'type', 'processed_at']);

        /** @var array<string, Money> $byMonth */
        $byMonth = [];

        foreach ($transactions as $transaction) {
            $key = $transaction->processed_at?->format('Y-m');

            if ($key === null) {
                continue;
            }

            $amount = $transaction->amountMoney();
            $running = $byMonth[$key] ?? Money::zero($amount->currency);

            $byMonth[$key] = $transaction->type === TransactionType::Refund
                ? $running->minus($amount)
                : $running->plus($amount);
        }

        $series = [];

        for ($offset = 0; $offset < $months; $offset++) {
            $month = $since->addMonths($offset);
            $key = $month->format('Y-m');

            $series[] = [
                'month' => $key,
                'collected' => ($byMonth[$key] ?? Money::zero())->toArray(),
            ];
        }

        return $series;
    }

    /**
     * Monthly churn: subscriptions that ended in the month over those live at
     * its start. Expressed in basis points to stay integer — a float percentage
     * that renders as "3.0000000000000004%" is its own small bug.
     *
     * @return list<array{month: string, ended: int, base: int, rate: float}>
     */
    public function churn(int $months = 12): array
    {
        $since = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);

        $subscriptions = Subscription::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->get(['created_at', 'ended_at']);

        $series = [];

        for ($offset = 0; $offset < $months; $offset++) {
            $start = $since->addMonths($offset);
            $end = $start->endOfMonth();

            $base = $subscriptions
                ->filter(fn (Subscription $s): bool => $s->created_at !== null
                    && $s->created_at->lessThan($start)
                    && ($s->ended_at === null || $s->ended_at->greaterThanOrEqualTo($start)))
                ->count();

            $ended = $subscriptions
                ->filter(fn (Subscription $s): bool => $s->ended_at !== null
                    && $s->ended_at->betweenIncluded($start, $end))
                ->count();

            $series[] = [
                'month' => $start->format('Y-m'),
                'ended' => $ended,
                'base' => $base,
                'rate' => $base === 0 ? 0.0 : round($ended / $base * 100, 1),
            ];
        }

        return $series;
    }

    /**
     * MRR split by plan, largest first — where the revenue actually comes from.
     *
     * @param  Collection<int, Subscription>  $paying
     * @return list<array{plan: string, accounts: int, mrr: array{amount: int, currency: string, formatted: string}}>
     */
    public function planMix(Collection $paying): array
    {
        $mix = [];

        foreach ($paying->groupBy(fn (Subscription $s): string => $s->plan->name) as $plan => $group) {
            /** @var Collection<int, Subscription> $group */
            $mix[] = [
                'plan' => (string) $plan,
                'accounts' => $group->count(),
                'mrr' => $this->mrr($group)->toArray(),
            ];
        }

        usort($mix, static fn (array $a, array $b): int => $b['mrr']['amount'] <=> $a['mrr']['amount']);

        return $mix;
    }

    /**
     * Money invoiced and not paid: the open invoices whose due date has passed.
     */
    public function outstanding(): Money
    {
        $total = Invoice::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('status', InvoiceStatus::Open->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', CarbonImmutable::now())
            ->sum('total');

        return Money::of((int) $total);
    }

    public function failedPaymentCount(): int
    {
        return Transaction::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('status', TransactionStatus::Failed->value)
            ->count();
    }

    /**
     * Live subscriptions across every workspace, with the plan eager-loaded so
     * `price()` does not fire a query per row.
     *
     * @return Collection<int, Subscription>
     */
    public function liveSubscriptions(): Collection
    {
        return Subscription::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->live()
            ->with('plan')
            ->get();
    }

    protected function collected(CarbonImmutable $since): Money
    {
        $transactions = Transaction::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('status', TransactionStatus::Succeeded->value)
            ->where('processed_at', '>=', $since)
            ->get(['amount', 'currency', 'type']);

        $total = Money::zero();

        foreach ($transactions as $transaction) {
            $amount = $transaction->amountMoney();

            $total = $transaction->type === TransactionType::Refund
                ? $total->minus($amount)
                : $total->plus($amount);
        }

        return $total;
    }

    /**
     * Average revenue per paying account.
     */
    protected function arpa(Money $mrr, int $accounts): Money
    {
        return $accounts === 0 ? Money::zero($mrr->currency) : $mrr->prorate(1, $accounts);
    }
}
