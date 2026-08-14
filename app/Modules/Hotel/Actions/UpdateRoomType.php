<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\RoomTypeData;
use App\Modules\Hotel\Models\RoomType;

class UpdateRoomType
{
    public function handle(RoomType $roomType, RoomTypeData $data): RoomType
    {
        $roomType->fill($data->toUpdateAttributes())->save();

        if (in_array('facility_ids', $data->provided, true)) {
            $roomType->facilities()->sync($data->facilityIds);
        }

        return $roomType;
    }
}