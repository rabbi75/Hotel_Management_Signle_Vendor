<?php

declare(strict_types=1);

namespace App\Modules\Guest\Actions;

use App\Modules\Guest\DTOs\GuestData;
use App\Modules\Guest\Models\Guest;

class CreateGuest
{
    public function handle(GuestData $data): Guest
    {
        $guest = new Guest($data->toAttributes());
        $guest->save();

        return $guest;
    }
}
