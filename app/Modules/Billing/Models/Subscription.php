<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Support\Money;
use App\Modules\Company\Models\Company;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $plan_id
 * @property string $gateway
 * @property string|null $gateway_id
 * @property SubscriptionStatus $status
 * @property BillingInterval $interval
 * @property int $quantity
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $current_period_start
 * @property CarbonImmutable|null $current_period_end
 * @property CarbonImmutable|null $cancels_at
 * @property CarbonImmutable|null $ended_at
 * @property int $dunning_attempts
 * @property CarbonImmutable|null $past_due_since
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Plan $plan
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'plan_id',
        'gateway',
        'gateway_id',
        'status',
        'interval',
        'quantity',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancels_at',
        'ended_at',
        'dunning_attempts',
        'past_due_since',
    ];

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<UsageRecord, $this>
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', [
            SubscriptionStatus::Trialing->value,
            SubscriptionStatus::Active->value,
            SubscriptionStatus::PastDue->value,
        ]);
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function isCancelling(): bool
    {
        return $this->cancels_at !== null && $this->status->isLive();
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }

    public function price(): Money
    {
        return $this->plan->priceFor($this->interval)->multipliedBy($this->quantity);
    }

    /**
     * Fraction of the current period still unused, as [remaining, total] whole
     * seconds. Returned as a pair so proration stays integer arithmetic.
     *
     * @return array{0: int, 1: int}
     */
    public function remainingPeriod(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $start = $this->current_period_start;
        $end = $this->current_period_end;

        if ($start === null || $end === null || $end->lessThanOrEqualTo($start)) {
            return [0, 0];
        }

        $total = $end->getTimestamp() - $start->getTimestamp();
        $remaining = max(0, min($total, $end->getTimestamp() - $now->getTimestamp()));

        return [$remaining, $total];
    }

    /**
     * A subscription's plan decides which feature-gated nav items a workspace's
     * members see, so any lifecycle change — subscribe, swap, cancel, resume,
     * or an admin grant — must drop their cached navigation. Hooking the model
     * rather than each action keeps the four call paths from drifting apart.
     */
    protected static function booted(): void
    {
        $flush = static function (self $subscription): void {
            $company = $subscription->company()->withoutGlobalScope(CompanyScope::class)->first();

            if ($company instanceof Company) {
                app(NavigationBuilder::class)->flushForCompany($company);
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'interval' => BillingInterval::class,
            'quantity' => 'integer',
            'dunning_attempts' => 'integer',
            'trial_ends_at' => 'immutable_datetime',
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'cancels_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'past_due_since' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
