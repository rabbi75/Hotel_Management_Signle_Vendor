<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Requests;

use App\Modules\OnlineBooking\Models\BookingPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route('booking_payment_method');

        return $method instanceof BookingPaymentMethod
            && $this->user()?->can('update', $method);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'is_enabled' => ['required', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
