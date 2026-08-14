<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\Models\RoomType;

class DeleteRoomType
{
    public function handle(RoomType $roomType): void
    {
        $roomType->facilities()->detach();
        $roomType->delete();
    }
}