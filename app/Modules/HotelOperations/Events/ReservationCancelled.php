<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Events;

use App\Modules\Reservation\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Reservation $reservation) {}
}
