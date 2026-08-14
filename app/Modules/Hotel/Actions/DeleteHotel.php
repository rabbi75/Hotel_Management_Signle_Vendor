<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\Models\Hotel;
use App\Support\Tenancy\CurrentHotel;

class DeleteHotel
{
    public function handle(Hotel $hotel): void
    {
        $current = app(CurrentHotel::class);

        if ($current->id() === $hotel->id) {
            $current->forget();
            session()->forget((string) config('saas.hotel.session_key'));
        }

        $hotel->delete();
    }
}