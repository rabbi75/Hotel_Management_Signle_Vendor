<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlacePublicBookingRequest extends FormRequest
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
            'payment_method_id' => ['required', 'integer'],
            'create_account' => ['sometimes', 'boolean'],
            'password' => ['required_if:create_account,true', 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
