<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Http\Requests;

use App\Modules\Reservation\Models\Reservation;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $reservation = $this->route('reservation');

        return $user instanceof User
            && $reservation instanceof Reservation
            && $user->can('update', $reservation);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
            'bed_id' => ['nullable', 'integer', Rule::exists('beds', 'id')->where('company_id', current_company_id())->whereNull('deleted_at')],
        ];
    }
}
