<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PaymentMethod $method */
        $method = $this->resource;

        return [
            'id' => $method->id,
            'gateway' => $method->gateway,
            'type' => $method->type,
            'brand' => $method->brand,
            'last_four' => $method->last_four,
            'exp_month' => $method->exp_month,
            'exp_year' => $method->exp_year,
            'holder_name' => $method->holder_name,
            'is_default' => $method->is_default,
            'is_expired' => $method->isExpired(),
            'created_at' => $method->created_at?->toIso8601String(),
        ];
    }
}
