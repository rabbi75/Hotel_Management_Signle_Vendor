<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Models\Subscription;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwapPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $subscription = $this->route('subscription');

        return $user instanceof User
            && $subscription instanceof Subscription
            && $user->can('update', $subscription);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::exists('plans', 'slug')->where('is_active', true)->whereNull('deleted_at')],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
        ];
    }

    public function billingInterval(): BillingInterval
    {
        return BillingInterval::from((string) $this->string('interval'));
    }
}
