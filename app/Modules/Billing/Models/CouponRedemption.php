<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $coupon_id
 * @property int $company_id
 * @property int|null $subscription_id
 * @property int $amount_off
 * @property string $currency
 * @property CarbonImmutable $redeemed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CouponRedemption extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'coupon_id',
        'subscription_id',
        'amount_off',
        'currency',
        'redeemed_at',
    ];

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_off' => 'integer',
            'redeemed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
