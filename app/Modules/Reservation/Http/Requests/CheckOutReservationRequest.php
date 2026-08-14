<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Http\Requests;

use App\Modules\Reservation\Models\Reservation;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CheckOutReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $reservation = $this->route('reservation');

        return $user instanceof User
            && $reservation instanceof Reservation
            && $user->can('checkOut', $reservation);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
