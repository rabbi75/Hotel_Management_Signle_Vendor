<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A sellable plan.
 *
 * Not tenant-owned: the catalogue belongs to whoever operates the kit, and is
 * the same for every workspace.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property list<string> $features
 * @property list<string> $entitlements
 * @property array<string, int> $limits
 * @property int $monthly_price
 * @property int $yearly_price
 * @property string $currency
 * @property int $trial_days
 * @property bool $is_active
 * @property bool $is_public
 * @property int $sort
 * @property array<string, array<string, string>>|null $gateway_prices
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'features',
        'entitlements',
        'limits',
        'monthly_price',
        'yearly_price',
        'currency',
        'trial_days',
        'is_active',
        'is_public',
        'sort',
        'gateway_prices',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function priceFor(BillingInterval $interval): Money
    {
        return Money::of(
            $interval === BillingInterval::Yearly ? $this->yearly_price : $this->monthly_price,
            $this->currency,
        );
    }

    /**
     * What a year on this plan costs when paid monthly — the baseline the
     * "save X" badge on the plan picker is measured against.
     */
    public function yearlySavings(): Money
    {
        return $this->priceFor(BillingInterval::Monthly)
            ->multipliedBy(12)
            ->minus($this->priceFor(BillingInterval::Yearly))
            ->atLeastZero();
    }

    public function isFree(): bool
    {
        return $this->monthly_price === 0 && $this->yearly_price === 0;
    }

    /**
     * Whether this plan grants a feature. An unknown key is never granted, which
     * is what makes config/entitlements.php the closed vocabulary it claims to be.
     */
    public function grantsFeature(string $key): bool
    {
        return in_array($key, $this->entitlements, true);
    }

    /**
     * The ceiling for one limit. A missing key is unlimited, expressed as -1 so
     * callers never have to distinguish "absent" from "zero".
     */
    public function limit(string $key): int
    {
        return $this->limits[$key] ?? -1;
    }

    public function gatewayPrice(string $gateway, BillingInterval $interval): ?string
    {
        return $this->gateway_prices[$gateway][$interval->value] ?? null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            $plan->slug = $plan->slug !== '' ? $plan->slug : Str::slug($plan->name);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'entitlements' => 'array',
            'limits' => 'array',
            'gateway_prices' => 'array',
            'monthly_price' => 'integer',
            'yearly_price' => 'integer',
            'trial_days' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
