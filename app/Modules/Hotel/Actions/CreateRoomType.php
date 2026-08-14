<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\RoomTypeData;
use App\Modules\Hotel\Models\RoomType;

class CreateRoomType
{
    public function handle(RoomTypeData $data): RoomType
    {
        $roomType = new RoomType($data->toAttributes());
        $roomType->save();

        if ($data->facilityIds !== []) {
            $roomType->facilities()->sync($data->facilityIds);
        }

        return $roomType;
    }
}