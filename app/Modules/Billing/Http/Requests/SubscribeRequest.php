<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Models\Subscription;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Subscription::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::exists('plans', 'slug')->where('is_active', true)->whereNull('deleted_at')],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
            'coupon' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['nullable', 'string', 'max:255'],

            // Which processor the customer picked. Validated as a known driver
            // only; whether it is *offered for this plan* is re-checked in
            // StartCheckout, because that depends on the plan's currency and
            // cannot be expressed here.
            'gateway' => ['nullable', 'string', Rule::exists('payment_gateways', 'driver')->where('is_enabled', true)],
        ];
    }

    public function billingInterval(): BillingInterval
    {
        return BillingInterval::from((string) $this->string('interval'));
    }

    public function couponCode(): ?string
    {
        $code = strtoupper(trim((string) $this->string('coupon')));

        return $code === '' ? null : $code;
    }
}
