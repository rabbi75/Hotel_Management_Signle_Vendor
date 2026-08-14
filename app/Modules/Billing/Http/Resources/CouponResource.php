<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Enums\CouponType;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Coupon
 */
class CouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Coupon $coupon */
        $coupon = $this->resource;

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'description' => $coupon->description,
            'type' => $coupon->type->value,
            'type_label' => $coupon->type->label(),
            'value' => $coupon->value,
            'value_formatted' => $coupon->type === CouponType::Percent
                ? $coupon->value.'%'
                : Money::of($coupon->value, $coupon->currency)->format(),
            'currency' => $coupon->currency,
            'max_redemptions' => $coupon->max_redemptions,
            'redeemed_count' => $coupon->redeemed_count,
            'expires_at' => $coupon->expires_at?->toIso8601String(),
            'plan_ids' => $coupon->plan_ids ?? [],
            'is_active' => $coupon->is_active,
            'is_expired' => $coupon->isExpired(),
            'is_exhausted' => $coupon->isExhausted(),
            'is_redeemable' => $coupon->isRedeemable(),
            'created_at' => $coupon->created_at?->toIso8601String(),
        ];
    }
}
