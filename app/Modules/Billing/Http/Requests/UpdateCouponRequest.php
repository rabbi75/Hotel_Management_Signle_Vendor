<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\CouponType;
use App\Modules\Billing\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('platform.coupons.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $coupon = $this->route('coupon');

        return [
            'code' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('coupons', 'code')->ignore($coupon instanceof Coupon ? $coupon->id : null),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'min:1', $this->valueCeiling()],
            'currency' => ['nullable', 'string', 'size:3'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'expires_at' => ['nullable', 'date'],
            'plan_ids' => ['nullable', 'array'],
            'plan_ids.*' => ['integer', Rule::exists('plans', 'id')->whereNull('deleted_at')],
            'is_active' => ['boolean'],
        ];
    }

    protected function valueCeiling(): string
    {
        return $this->string('type')->toString() === CouponType::Percent->value ? 'max:100' : 'max:1000000';
    }
}
