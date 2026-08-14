<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Http\Requests;

use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $reservation = $this->route('reservation');

        return $user instanceof User && $reservation instanceof Reservation && $user->can('update', $reservation);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'hotel_id' => ['sometimes', 'required', 'integer', Rule::exists('hotels', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'guest_id' => ['sometimes', 'required', 'integer', Rule::exists('guests', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'bed_id' => ['nullable', 'integer', Rule::exists('beds', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'room_type_id' => ['nullable', 'integer', Rule::exists('room_types', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'check_in_date' => ['sometimes', 'required', 'date'],
            'check_out_date' => ['sometimes', 'required', 'date', 'after:check_in_date'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'rooms_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'booking_source' => ['nullable', Rule::enum(BookingSource::class)],
            'special_requests' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'tax' => ['nullable', 'integer', 'min:0'],
            'total' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
        ];
    }
}
