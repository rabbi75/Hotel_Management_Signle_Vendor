<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPublicPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'min:4', 'max:120'],
        ];
    }
}
