<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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
            'room_type_id' => ['required', 'integer'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'guest' => ['required', 'array'],
            'guest.first_name' => ['required', 'string', 'max:120'],
            'guest.last_name' => ['required', 'string', 'max:120'],
            'guest.email' => ['nullable', 'email', 'max:255'],
            'guest.phone' => ['nullable', 'string', 'max:40'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'channel_metadata' => ['nullable', 'array'],
        ];
    }
}
