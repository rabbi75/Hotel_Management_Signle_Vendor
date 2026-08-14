<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Money;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The numbers on the platform dashboard.
 *
 * Every query runs inside {@see CurrentCompany::bypass()}: the admin routes drop
 * SetCurrentCompany, so no tenant is normally resolved, but an impersonating or
 * mid-flight request might have one — bypassing makes the cross-tenant intent
 * explicit rather than incidental.
 */
class PlatformMetrics
{
    public function __construct(
        protected CurrentCompany $tenant,
        protected RevenueMetrics $revenue,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->tenant->bypass(function (): array {
            $live = Subscription::query()->live()->with('plan')->get();

            return [
                'tenants' => [
                    'total' => Company::query()->count(),
                    'active' => Company::query()->where('is_active', true)->count(),
                    'new_this_month' => Company::query()
                        ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
                        ->count(),
                ],
                'users' => [
                    'total' => User::query()->count(),
                ],
                'subscriptions' => [
                    'active' => $live->where('status', SubscriptionStatus::Active)->count(),
                    'trialing' => $live->where('status', SubscriptionStatus::Trialing)->count(),
                    'past_due' => $live->where('status', SubscriptionStatus::PastDue)->count(),
                    'canceling' => $live->filter->isCancelling()->count(),
                ],
                'mrr' => $this->mrr($live)->toArray(),
                'trials_ending' => $this->trialsEndingSoon(),
            ];
        });
    }

    /**
     * Workspace signups per month for the last year, oldest first.
     *
     * @return list<array{month: string, count: int}>
     */
    public function signups(int $months = 12): array
    {
        return $this->tenant->bypass(function () use ($months): array {
            $since = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);

            $counts = Company::query()
                ->where('created_at', '>=', $since)
                ->get(['created_at'])
                ->countBy(static fn (Company $company): string => $company->created_at?->format('Y-m') ?? '');

            $series = [];

            for ($offset = 0; $offset < $months; $offset++) {
                $month = $since->addMonths($offset);

                $series[] = [
                    'month' => $month->format('Y-m'),
                    'count' => (int) ($counts[$month->format('Y-m')] ?? 0),
                ];
            }

            return $series;
        });
    }

    /**
     * Monthly recurring revenue.
     *
     * Delegated to {@see RevenueMetrics::mrr()} so the dashboard tile and the
     * revenue screen can never disagree about what MRR means.
     *
     * @param  Collection<int, Subscription>  $subscriptions
     */
    protected function mrr(mixed $subscriptions): Money
    {
        return $this->revenue->mrr($subscriptions);
    }

    /**
     * @return list<array{company: string, uuid: string, trial_ends_at: string}>
     */
    protected function trialsEndingSoon(int $days = 7): array
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Trialing->value)
            ->whereBetween('trial_ends_at', [CarbonImmutable::now(), CarbonImmutable::now()->addDays($days)])
            ->with('company')
            ->orderBy('trial_ends_at')
            ->limit(10)
            ->get()
            ->map(static fn (Subscription $subscription): array => [
                'company' => $subscription->company?->name ?? '',
                'uuid' => $subscription->company?->uuid ?? '',
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String() ?? '',
            ])
            ->all();
    }
}
