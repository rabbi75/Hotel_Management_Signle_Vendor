<?php

declare(strict_types=1);

namespace App\Modules\Guest\Actions;

use App\Modules\Guest\Models\Guest;
use App\Modules\Reservation\Enums\ReservationStatus;
use Illuminate\Validation\ValidationException;

class DeleteGuest
{
    public function handle(Guest $guest): void
    {
        $hasActive = $guest->reservations()
            ->whereIn('status', [
                ReservationStatus::Pending->value,
                ReservationStatus::Confirmed->value,
                ReservationStatus::CheckedIn->value,
            ])
            ->exists();

        if ($hasActive) {
            throw ValidationException::withMessages([
                'guest' => __('This guest has an active reservation and cannot be deleted.'),
            ]);
        }

        $guest->delete();
    }
}
