<?php

declare(strict_types=1);

namespace App\Modules\Guest\Actions;

use App\Modules\Guest\DTOs\GuestData;
use App\Modules\Guest\Models\Guest;

class UpdateGuest
{
    public function handle(Guest $guest, GuestData $data): Guest
    {
        $guest->fill($data->toUpdateAttributes())->save();

        return $guest;
    }
}
