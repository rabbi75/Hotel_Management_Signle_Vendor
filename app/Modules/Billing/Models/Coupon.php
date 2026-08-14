<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\CouponType;
use App\Modules\Billing\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string|null $description
 * @property CouponType $type
 * @property int $value
 * @property string|null $currency
 * @property int|null $max_redemptions
 * @property int $redeemed_count
 * @property CarbonImmutable|null $expires_at
 * @property list<int>|null $plan_ids
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'currency',
        'max_redemptions',
        'expires_at',
        'plan_ids',
        'is_active',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isExpired(?CarbonImmutable $now = null): bool
    {
        return $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo($now ?? CarbonImmutable::now());
    }

    public function isExhausted(): bool
    {
        return $this->max_redemptions !== null && $this->redeemed_count >= $this->max_redemptions;
    }

    public function appliesToPlan(Plan $plan): bool
    {
        return $this->plan_ids === null || $this->plan_ids === [] || in_array($plan->id, $this->plan_ids, true);
    }

    public function isRedeemable(?Plan $plan = null, ?CarbonImmutable $now = null): bool
    {
        return $this->is_active
            && ! $this->isExpired($now)
            && ! $this->isExhausted()
            && ($plan === null || $this->appliesToPlan($plan));
    }

    /**
     * The amount this coupon takes off a given price, never more than the price
     * itself.
     */
    public function discountFor(Money $price): Money
    {
        $off = match ($this->type) {
            CouponType::Percent => $price->percentage($this->value),
            CouponType::Fixed => Money::of($this->value, $price->currency),
        };

        return $off->greaterThan($price) ? $price : $off;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
            'plan_ids' => 'array',
            'is_active' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
