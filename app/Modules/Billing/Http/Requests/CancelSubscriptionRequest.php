<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\Subscription;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $subscription = $this->route('subscription');

        return $user instanceof User
            && $subscription instanceof Subscription
            && $user->can('cancel', $subscription);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'immediately' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
