<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\UsageRecord;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Closure;

/**
 * Plan limit enforcement, and the single question every other module asks
 * before it lets a workspace consume something:
 *
 *     if (! $limits->can('seats')) { ... }
 *
 * A limit of -1 (or an unlisted key) is unlimited. Billing being disabled makes
 * everything unlimited, so a kit deployed without a processor is never blocked
 * by a plan nobody bought.
 */
class SubscriptionLimits
{
    /**
     * Metric resolvers registered by the modules that own the resource.
     *
     * @var array<string, Closure(Company): int>
     */
    protected static array $resolvers = [];

    public function __construct(protected CurrentCompany $tenant) {}

    /**
     * @param  Closure(Company): int  $resolver
     */
    public static function resolveUsing(string $metric, Closure $resolver): void
    {
        static::$resolvers[$metric] = $resolver;
    }

    public static function forgetResolvers(): void
    {
        static::$resolvers = [];
    }

    /**
     * Whether $requested more of $limit fits inside the plan.
     */
    public function can(string $limit, int $requested = 1, ?Company $company = null): bool
    {
        $company ??= $this->company();

        if (! $company instanceof Company || ! $this->enabled()) {
            return true;
        }

        $ceiling = $this->limit($limit, $company);

        if ($ceiling < 0) {
            return true;
        }

        return $this->used($limit, $company) + $requested <= $ceiling;
    }

    /**
     * @throws BillingException when the limit would be exceeded.
     */
    public function ensure(string $limit, int $requested = 1, ?Company $company = null): void
    {
        if ($this->can($limit, $requested, $company)) {
            return;
        }

        throw new BillingException(__('Your plan\'s :limit allowance has been reached. Upgrade to continue.', [
            'limit' => str_replace('_', ' ', $limit),
        ]));
    }

    /**
     * Whether the workspace's plan grants a feature.
     *
     * Billing disabled makes everything available, mirroring {@see can()}: a kit
     * deployed without a processor must not have half its modules dark. With no
     * live plan, only features the free tier would grant are denied — that policy
     * lives in the plan catalogue, not here, so "no plan" simply grants nothing.
     */
    public function hasFeature(string $key, ?Company $company = null): bool
    {
        $company ??= $this->company();

        if (! $company instanceof Company || ! $this->enabled()) {
            return true;
        }

        return $this->plan($company)?->grantsFeature($key) ?? false;
    }

    /**
     * Every feature the current plan grants, for the shared props gate.
     *
     * @return list<string>
     */
    public function features(?Company $company = null): array
    {
        $company ??= $this->company();

        // Billing off ⇒ every declared feature is available, so the client gate
        // never hides a module the server would actually serve.
        if (! $company instanceof Company || ! $this->enabled()) {
            return array_keys((array) config('entitlements.features', []));
        }

        return $this->plan($company)?->entitlements ?? [];
    }

    /**
     * The plan ceiling for one metric; -1 when unlimited.
     */
    public function limit(string $key, ?Company $company = null): int
    {
        return $this->plan($company)?->limit($key) ?? -1;
    }

    public function used(string $key, ?Company $company = null): int
    {
        $company ??= $this->company();

        if (! $company instanceof Company) {
            return 0;
        }

        $resolver = static::$resolvers[$key] ?? null;

        if ($resolver instanceof Closure) {
            return $resolver($company);
        }

        return $this->meteredUsage($key, $company);
    }

    /**
     * How much headroom is left; -1 when unlimited.
     */
    public function remaining(string $key, ?Company $company = null): int
    {
        $ceiling = $this->limit($key, $company);

        return $ceiling < 0 ? -1 : max(0, $ceiling - $this->used($key, $company));
    }

    public function plan(?Company $company = null): ?Plan
    {
        return $this->subscription($company)?->plan;
    }

    public function subscription(?Company $company = null): ?Subscription
    {
        $company ??= $this->company();

        if (! $company instanceof Company) {
            return null;
        }

        return Subscription::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->live()
            ->with('plan')
            ->latest('id')
            ->first();
    }

    /**
     * Every limit the current plan declares, shaped for the usage meters on the
     * billing screen.
     *
     * @return list<array{key: string, label: string, limit: int, used: int, remaining: int, percentage: int|null}>
     */
    public function meters(?Company $company = null): array
    {
        $company ??= $this->company();
        $plan = $this->plan($company);

        if (! $plan instanceof Plan || ! $company instanceof Company) {
            return [];
        }

        $meters = [];

        foreach ($plan->limits as $key => $ceiling) {
            $used = $this->used($key, $company);

            $meters[] = [
                'key' => $key,
                'label' => str($key)->replace('_', ' ')->headline()->toString(),
                'limit' => $ceiling,
                'used' => $used,
                'remaining' => $ceiling < 0 ? -1 : max(0, $ceiling - $used),
                'percentage' => $ceiling > 0 ? (int) min(100, intdiv($used * 100, $ceiling)) : null,
            ];
        }

        return $meters;
    }

    protected function enabled(): bool
    {
        return (bool) config('saas.billing.enabled', false);
    }

    protected function company(): ?Company
    {
        return $this->tenant->get();
    }

    /**
     * Sum of the usage ledger for the current billing period, falling back to
     * the calendar month when there is no subscription to anchor to.
     */
    protected function meteredUsage(string $metric, Company $company): int
    {
        $subscription = $this->subscription($company);

        $since = $subscription instanceof Subscription && $subscription->current_period_start !== null
            ? $subscription->current_period_start
            : now()->startOfMonth();

        return (int) UsageRecord::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('metric', $metric)
            ->where('recorded_at', '>=', $since)
            ->sum('quantity');
    }
}
