<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Requests;

use App\Modules\OnlineBooking\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') instanceof Customer;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $customer = $this->user('customer');

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer?->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
