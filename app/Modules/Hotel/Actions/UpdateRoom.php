<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\RoomData;
use App\Modules\Hotel\Models\Room;

class UpdateRoom
{
    public function handle(Room $room, RoomData $data): Room
    {
        $room->fill($data->toUpdateAttributes())->save();

        if (in_array('facility_ids', $data->provided, true)) {
            $room->facilities()->sync($data->facilityIds);
        }

        return $room;
    }
}