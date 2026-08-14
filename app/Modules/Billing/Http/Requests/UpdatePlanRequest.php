<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('platform.plans.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                'alpha_dash',
                Rule::unique('plans', 'slug')->ignore($plan instanceof Plan ? $plan->id : null),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'features' => ['array', 'max:30'],
            'features.*' => ['string', 'max:200'],
            'entitlements' => ['array'],
            'entitlements.*' => ['string', Rule::in(array_keys((array) config('entitlements.features', [])))],
            'limits' => ['array'],
            'limits.*' => ['integer', 'min:-1', 'max:100000000'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'currency' => ['required', 'string', 'size:3'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
            'sort' => ['integer', 'min:0', 'max:9999'],
            'gateway_prices' => ['nullable', 'array'],
            'gateway_prices.*' => ['array'],
            'gateway_prices.*.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
