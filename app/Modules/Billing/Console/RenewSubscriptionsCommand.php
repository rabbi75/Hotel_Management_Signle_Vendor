<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Actions\GenerateInvoice;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\PaymentFailedNotification;
use App\Modules\Billing\Notifications\SubscriptionCancelledNotification;
use App\Modules\Billing\Notifications\TrialEndingNotification;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Advances the subscription lifecycle: trials, renewals, grace and dunning.
 *
 * Runs unscoped by design — it sweeps every workspace — so every query lifts
 * the tenant scope explicitly rather than relying on there being no session.
 */
class RenewSubscriptionsCommand extends Command
{
    /** Days before a trial ends that the reminder goes out. */
    private const TRIAL_REMINDER_DAYS = 3;

    protected $signature = 'billing:renew {--dry-run : Report what would change without changing it}';

    protected $description = 'Process trial expiry, renewals, grace periods and dunning';

    public function handle(GenerateInvoice $generateInvoice): int
    {
        $now = CarbonImmutable::now();
        $dryRun = (bool) $this->option('dry-run');

        $this->remindExpiringTrials($now, $dryRun);
        $this->endCancelledSubscriptions($now, $dryRun);
        $this->renewLapsedPeriods($now, $dryRun, $generateInvoice);
        $this->expirePastDue($now, $dryRun);

        return self::SUCCESS;
    }

    protected function remindExpiringTrials(CarbonImmutable $now, bool $dryRun): void
    {
        $subscriptions = $this->query()
            ->where('status', SubscriptionStatus::Trialing->value)
            ->whereBetween('trial_ends_at', [$now, $now->addDays(self::TRIAL_REMINDER_DAYS)])
            ->with(['plan', 'company.owner'])
            ->get();

        foreach ($subscriptions as $subscription) {
            $days = max(1, (int) $now->diffInDays($subscription->trial_ends_at, absolute: true));

            if (! $dryRun) {
                $subscription->company->owner->notify(new TrialEndingNotification($subscription, $days));
            }
        }

        $this->components->info(sprintf(
            '%s %d trial reminder(s).',
            $dryRun ? 'Would send' : 'Sent',
            $subscriptions->count(),
        ));
    }

    protected function endCancelledSubscriptions(CarbonImmutable $now, bool $dryRun): void
    {
        $subscriptions = $this->query()
            ->live()
            ->whereNotNull('cancels_at')
            ->where('cancels_at', '<=', $now)
            ->with(['plan', 'company.owner'])
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($dryRun) {
                continue;
            }

            $subscription->status = SubscriptionStatus::Canceled;
            $subscription->ended_at = $now;
            $subscription->save();

            $subscription->company->owner->notify(new SubscriptionCancelledNotification($subscription));
        }

        $this->components->info(sprintf(
            '%s %d subscription(s).',
            $dryRun ? 'Would end' : 'Ended',
            $subscriptions->count(),
        ));
    }

    /**
     * Trials that have matured and paid periods that have lapsed both land here:
     * both need the next period opened and an invoice raised.
     */
    protected function renewLapsedPeriods(CarbonImmutable $now, bool $dryRun, GenerateInvoice $generateInvoice): void
    {
        $subscriptions = $this->query()
            ->live()
            ->whereNull('cancels_at')
            ->where(function (Builder $query) use ($now): void {
                $query->where('current_period_end', '<=', $now)
                    ->orWhere(function (Builder $inner) use ($now): void {
                        $inner->where('status', SubscriptionStatus::Trialing->value)
                            ->where('trial_ends_at', '<=', $now);
                    });
            })
            ->with(['plan', 'company.owner'])
            ->get();

        $renewed = 0;

        foreach ($subscriptions as $subscription) {
            if ($dryRun) {
                $renewed++;

                continue;
            }

            $wasTrialing = $subscription->status === SubscriptionStatus::Trialing;

            // A matured trial keeps the period the trial already opened; a
            // lapsed paid period rolls forward from where it ended.
            if (! $wasTrialing) {
                $start = $subscription->current_period_end ?? $now;
                $subscription->current_period_start = $start;
                $subscription->current_period_end = $subscription->interval->advance($start);
            }

            $subscription->status = SubscriptionStatus::Active;
            $subscription->save();

            if (! $subscription->plan->isFree()) {
                $generateInvoice->handle($subscription, null, InvoiceStatus::Open);
            }

            $renewed++;
        }

        $this->components->info(sprintf(
            '%s %d subscription(s).',
            $dryRun ? 'Would renew' : 'Renewed',
            $renewed,
        ));
    }

    /**
     * Past-due beyond the configured grace window loses access.
     */
    protected function expirePastDue(CarbonImmutable $now, bool $dryRun): void
    {
        $graceDays = (int) config('saas.billing.grace_days', 3);

        $subscriptions = $this->query()
            ->where('status', SubscriptionStatus::PastDue->value)
            ->whereNotNull('past_due_since')
            ->where('past_due_since', '<=', $now->subDays($graceDays))
            ->with(['plan', 'company.owner'])
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($dryRun) {
                continue;
            }

            $subscription->status = SubscriptionStatus::Expired;
            $subscription->ended_at = $now;
            $subscription->save();

            $subscription->company->owner->notify(new PaymentFailedNotification(
                $subscription,
                $subscription->dunning_attempts,
                0,
            ));
        }

        $this->components->info(sprintf(
            '%s %d past-due subscription(s) after a %d day grace period.',
            $dryRun ? 'Would expire' : 'Expired',
            $subscriptions->count(),
            $graceDays,
        ));
    }

    /**
     * @return Builder<Subscription>
     */
    protected function query(): Builder
    {
        return Subscription::query()->withoutGlobalScope(CompanyScope::class);
    }
}
