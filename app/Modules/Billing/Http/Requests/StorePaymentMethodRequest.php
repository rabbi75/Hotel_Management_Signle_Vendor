<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\PaymentMethod;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', PaymentMethod::class);
    }

    /**
     * Only a gateway-issued token is accepted. Raw card details must never
     * reach this application, so there is deliberately no rule for them.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'make_default' => ['boolean'],
        ];
    }
}
