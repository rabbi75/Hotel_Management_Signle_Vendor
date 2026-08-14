<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\Models\Room;

class DeleteRoom
{
    public function handle(Room $room): void
    {
        $room->facilities()->detach();
        $room->delete();
    }
}